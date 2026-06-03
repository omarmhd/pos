<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\SalesQuotation;
use App\Models\SalesQuotationItem;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Services\PricingService;
use App\Services\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class SalesQuotationController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:quotations.view')->only(['index', 'show']);
        $this->middleware('can:quotations.create')->only(['create', 'store']);
        $this->middleware('can:quotations.send')->only(['send']);
        $this->middleware('can:quotations.cancel')->only(['reject']);
        $this->middleware('can:quotations.convert')->only(['convertToOrderForm', 'convertToOrder']);
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $branchId = $this->effectiveBranchId($request);
            $query = SalesQuotation::with('customer', 'user')
                ->select('sales_quotations.*')
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId));

            return DataTables::eloquent($query)
                ->addColumn('customer_name_col', fn($q) => e($q->displayName()))
                ->addColumn('user_name',         fn($q) => e($q->user?->name ?? '—'))
                ->addColumn('total_fmt',         fn($q) => number_format($q->total_amount, 2))
                ->addColumn('status_badge',      fn($q) => $this->statusBadge($q))
                ->addColumn('date',              fn($q) => $q->quotation_date->format('Y-m-d'))
                ->addColumn('valid',             fn($q) => $q->valid_until?->format('Y-m-d') ?? '—')
                ->addColumn('action',            fn($q) => $this->actionButtons($q))
                ->rawColumns(['status_badge', 'action'])
                ->make(true);
        }

        $branches     = \App\Models\Branch::where('is_active', true)->orderBy('name')->get(['id','name','code']);
        $branchId     = $this->effectiveBranchId($request);
        $branchLocked = $this->isBranchLocked();
        $currency     = Setting::get('currency_symbol', 'ج.م');

        return view('sales-quotations.index', compact('branches', 'branchId', 'branchLocked', 'currency'));
    }

    public function create()
    {
        $customers  = Customer::where('is_active', true)->orderBy('name')->get(['id', 'name', 'phone']);
        $priceLists = PriceList::where('is_active', true)->orderBy('name')->get(['id', 'name', 'type']);
        $currency   = Setting::get('currency_symbol', 'ج.م');
        return view('sales-quotations.create', compact('customers', 'priceLists', 'currency'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id'      => 'nullable|exists:customers,id',
            'customer_name'    => 'required_without:customer_id|nullable|string|max:255',
            'quotation_date'   => 'required|date',
            'valid_until'      => 'nullable|date|after_or_equal:quotation_date',
            'price_list_id'    => 'nullable|exists:price_lists,id',
            'notes'            => 'nullable|string',
            'terms'            => 'nullable|string',
            'items'            => 'required|array|min:1',
            'items.*.product_id'      => 'required|exists:products,id',
            'items.*.quantity'        => 'required|numeric|min:0.001',
            'items.*.unit_price'      => 'required|numeric|min:0',
            'items.*.discount_percent'=> 'nullable|numeric|min:0|max:100',
        ]);

        DB::beginTransaction();
        try {
            $subtotal   = 0;
            $discountTot = 0;

            foreach ($request->items as $item) {
                $disc    = (float) ($item['discount_percent'] ?? 0);
                $lineTotal = round($item['quantity'] * $item['unit_price'] * (1 - $disc / 100), 2);
                $subtotal  += $item['quantity'] * $item['unit_price'];
                $discountTot += $item['quantity'] * $item['unit_price'] - $lineTotal;
            }

            $quot = SalesQuotation::create([
                'customer_id'     => $request->customer_id ?: null,
                'customer_name'   => $request->customer_name ?: null,
                'user_id'         => auth()->id(),
                'branch_id'       => auth()->user()->branch_id ?? Setting::get('default_branch_id'),
                'price_list_id'   => $request->price_list_id ?: null,
                'quotation_date'  => $request->quotation_date,
                'valid_until'     => $request->valid_until ?: null,
                'status'          => 'draft',
                'subtotal'        => round($subtotal, 2),
                'discount_amount' => round($discountTot, 2),
                'total_amount'    => round($subtotal - $discountTot, 2),
                'terms'           => $request->terms,
                'notes'           => $request->notes,
            ]);

            foreach ($request->items as $item) {
                $disc    = (float) ($item['discount_percent'] ?? 0);
                $lineTotal = round($item['quantity'] * $item['unit_price'] * (1 - $disc / 100), 2);
                SalesQuotationItem::create([
                    'quotation_id'     => $quot->id,
                    'product_id'       => $item['product_id'],
                    'quantity'         => $item['quantity'],
                    'unit_price'       => $item['unit_price'],
                    'discount_percent' => $disc,
                    'total_price'      => $lineTotal,
                    'notes'            => $item['notes'] ?? null,
                ]);
            }

            DB::commit();
            return redirect()->route('sales-quotations.show', $quot)
                ->with('success', 'تم إنشاء عرض السعر — ' . $quot->quotation_number);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'خطأ: ' . $e->getMessage());
        }
    }

    public function show(SalesQuotation $salesQuotation)
    {
        $salesQuotation->load('customer', 'user', 'branch', 'priceList', 'items.product', 'salesOrders');
        $currency = Setting::get('currency_symbol', 'ج.م');
        return view('sales-quotations.show', compact('salesQuotation', 'currency'));
    }

    /** Send quotation to customer */
    public function send(SalesQuotation $salesQuotation)
    {
        if ($salesQuotation->status !== 'draft') {
            return back()->with('error', 'يمكن إرسال عروض الأسعار بحالة "مسودة" فقط');
        }
        $salesQuotation->update(['status' => 'sent']);
        return back()->with('success', 'تم تغيير الحالة إلى "مُرسَل"');
    }

    /** Reject quotation */
    public function reject(SalesQuotation $salesQuotation)
    {
        $salesQuotation->update(['status' => 'rejected']);
        return back()->with('success', 'تم تسجيل رفض العرض');
    }

    /** Show form to convert quotation → Sales Order */
    public function convertToOrderForm(SalesQuotation $salesQuotation)
    {
        if (!$salesQuotation->canConvert()) {
            return redirect()->route('sales-quotations.show', $salesQuotation)
                ->with('error', 'لا يمكن تحويل هذا العرض في حالته الحالية');
        }
        $salesQuotation->load('items.product', 'customer');
        $warehouses = Warehouse::where('is_active', true)->with('branch:id,name')
            ->orderBy('is_default', 'desc')->get(['id','name','code','branch_id','is_default']);
        $defaultWarehouseId = WarehouseService::getDefault()->id;
        $currency = Setting::get('currency_symbol', 'ج.م');
        return view('sales-quotations.convert-to-order', compact(
            'salesQuotation', 'warehouses', 'defaultWarehouseId', 'currency'
        ));
    }

    /** Create Sales Order from quotation */
    public function convertToOrder(Request $request, SalesQuotation $salesQuotation)
    {
        $request->validate([
            'order_date'             => 'required|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'warehouse_id'           => 'nullable|exists:warehouses,id',
            'is_credit'              => 'boolean',
        ]);

        $warehouseId = WarehouseService::resolveId($request->input('warehouse_id'));

        DB::beginTransaction();
        try {
            $order = SalesOrder::create([
                'quotation_id'           => $salesQuotation->id,
                'customer_id'            => $salesQuotation->customer_id,
                'user_id'                => auth()->id(),
                'branch_id'              => $salesQuotation->branch_id,
                'warehouse_id'           => $warehouseId,
                'price_list_id'          => $salesQuotation->price_list_id,
                'order_date'             => $request->order_date,
                'expected_delivery_date' => $request->expected_delivery_date ?: null,
                'status'                 => 'confirmed',
                'is_credit'              => $request->boolean('is_credit'),
                'total_amount'           => $salesQuotation->total_amount,
                'notes'                  => $salesQuotation->notes,
            ]);

            foreach ($salesQuotation->items as $item) {
                SalesOrderItem::create([
                    'sales_order_id'   => $order->id,
                    'product_id'       => $item->product_id,
                    'quantity_ordered' => $item->quantity,
                    'unit_price'       => $item->discountedPrice(),
                    'total_price'      => $item->total_price,
                ]);
            }

            $salesQuotation->update(['status' => 'accepted']);

            DB::commit();
            return redirect()->route('sales-orders.show', $order)
                ->with('success', 'تم إنشاء أمر البيع — ' . $order->order_number);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'خطأ: ' . $e->getMessage());
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function statusBadge(SalesQuotation $q): string
    {
        $info = SalesQuotation::$statuses[$q->status] ?? ['label' => $q->status, 'color' => 'secondary'];
        $exp  = $q->isExpired() ? ' <span class="badge bg-warning ms-1 small">منتهي الصلاحية</span>' : '';
        return "<span class='badge bg-{$info['color']}'>{$info['label']}</span>" . $exp;
    }

    private function actionButtons(SalesQuotation $q): string
    {
        /** @var \App\Models\User|null $u */
        $u    = auth()->user();
        $show = '<a href="' . route('sales-quotations.show', $q) . '" class="btn btn-sm btn-info btn-action" title="عرض"><i class="bi bi-eye"></i></a>';
        $send = $conv = '';

        if ($q->status === 'draft' && $u?->can('quotations.send')) {
            $send = '<form action="' . route('sales-quotations.send', $q) . '" method="POST" class="d-inline">' . csrf_field() .
                '<button class="btn btn-sm btn-primary btn-action" title="إرسال"><i class="bi bi-send"></i></button></form>';
        }
        if ($q->canConvert() && $u?->can('quotations.convert')) {
            $conv = '<a href="' . route('sales-quotations.convert-to-order-form', $q) . '" class="btn btn-sm btn-success btn-action" title="تحويل لأمر بيع"><i class="bi bi-arrow-right-circle"></i></a>';
        }

        return '<div class="d-flex gap-1 flex-nowrap">' . $show . $send . $conv . '</div>';
    }
}
