<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Setting;
use App\Services\LedgerPostingService;
use App\Services\PricingService;
use App\Services\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class SalesOrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:sales_orders.view')->only(['index', 'show']);
        $this->middleware('can:sales_orders.confirm')->only(['confirm']);
        $this->middleware('can:sales_orders.cancel')->only(['cancel']);
        $this->middleware('can:sales_orders.convert')->only(['convertToInvoiceForm', 'convertToInvoice']);
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $branchId = $this->effectiveBranchId($request);
            $query = SalesOrder::with('customer', 'user')
                ->select('sales_orders.*')
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId));

            return DataTables::eloquent($query)
                ->addColumn('customer_name', fn($o) => e($o->customer?->name ?? '—'))
                ->addColumn('user_name',     fn($o) => e($o->user?->name ?? '—'))
                ->addColumn('total_fmt',     fn($o) => number_format($o->total_amount, 2))
                ->addColumn('status_badge',  fn($o) => $this->statusBadge($o))
                ->addColumn('date',          fn($o) => $o->order_date->format('Y-m-d'))
                ->addColumn('action',        fn($o) => $this->actionButtons($o))
                ->rawColumns(['status_badge', 'action'])
                ->make(true);
        }

        $branches     = \App\Models\Branch::where('is_active', true)->orderBy('name')->get(['id','name','code']);
        $branchId     = $this->effectiveBranchId($request);
        $branchLocked = $this->isBranchLocked();
        $currency     = Setting::get('currency_symbol', 'ج.م');

        return view('sales-orders.index', compact('branches', 'branchId', 'branchLocked', 'currency'));
    }

    public function show(SalesOrder $salesOrder)
    {
        $salesOrder->load('customer', 'user', 'branch', 'warehouse', 'quotation', 'items.product', 'invoices');
        $currency = Setting::get('currency_symbol', 'ج.م');
        return view('sales-orders.show', compact('salesOrder', 'currency'));
    }

    /** Confirm a draft order */
    public function confirm(SalesOrder $salesOrder)
    {
        if ($salesOrder->status !== 'draft') {
            return back()->with('error', 'يمكن تأكيد الأوامر بحالة "مسودة" فقط');
        }
        $salesOrder->update(['status' => 'confirmed']);
        return back()->with('success', 'تم تأكيد أمر البيع');
    }

    /** Cancel an order */
    public function cancel(SalesOrder $salesOrder)
    {
        if (in_array($salesOrder->status, ['fulfilled', 'cancelled'])) {
            return back()->with('error', 'لا يمكن إلغاء هذا الأمر');
        }
        $salesOrder->update(['status' => 'cancelled']);
        return back()->with('success', 'تم إلغاء أمر البيع');
    }

    /** Show the "convert to invoice" form */
    public function convertToInvoiceForm(SalesOrder $salesOrder)
    {
        if (!$salesOrder->canConvertToInvoice()) {
            return redirect()->route('sales-orders.show', $salesOrder)
                ->with('error', 'لا يمكن تحويل هذا الأمر في حالته الحالية');
        }
        $salesOrder->load('customer', 'items.product', 'warehouse');
        $currency = Setting::get('currency_symbol', 'ج.م');
        return view('sales-orders.convert', compact('salesOrder', 'currency'));
    }

    /** Create a Sale (invoice) from a Sales Order */
    public function convertToInvoice(Request $request, SalesOrder $salesOrder)
    {
        $request->validate([
            'payment_method' => 'required_unless:is_credit,1|nullable|in:cash,card,mobile_wallet',
            'is_credit'      => 'boolean',
            'items'          => 'required|array|min:1',
            'items.*.so_item_id' => 'required|exists:sales_order_items,id',
            'items.*.quantity'   => 'required|numeric|min:0.001',
        ]);

        $warehouseId = $salesOrder->warehouse_id
            ?? WarehouseService::getForUser(auth()->user())->id;

        DB::beginTransaction();
        try {
            // Build subtotal from items
            $subtotal = 0;
            foreach ($request->items as $item) {
                $soItem    = SalesOrderItem::findOrFail($item['so_item_id']);
                $subtotal += $item['quantity'] * (float) $soItem->unit_price;
            }

            $isCredit = $request->boolean('is_credit') || $salesOrder->is_credit;
            $vatRate  = (float) Setting::get('vat_rate', 0);
            $vatEnab  = (bool)  Setting::get('vat_enabled', 0);
            $tax      = $vatEnab ? round($subtotal * $vatRate / 100, 2) : 0;
            $total    = round($subtotal + $tax, 2);

            // Lock stock_levels before selling
            $productIds = collect($request->items)->map(function($i) {
                return SalesOrderItem::find($i['so_item_id'])?->product_id;
            })->filter()->unique()->values()->all();

            \App\Models\StockLevel::where('warehouse_id', $warehouseId)
                ->whereIn('product_id', $productIds)
                ->lockForUpdate()->get();

            \App\Models\Product::whereIn('id', $productIds)->lockForUpdate()->get();

            // Create the Sale
            $sale = Sale::create([
                'user_id'        => auth()->id(),
                'customer_id'    => $salesOrder->customer_id,
                'sales_order_id' => $salesOrder->id,
                'warehouse_id'   => $warehouseId,
                'branch_id'      => $salesOrder->branch_id,
                'is_credit'      => $isCredit,
                'subtotal'       => round($subtotal, 2),
                'discount'       => 0,
                'tax'            => $tax,
                'total_amount'   => $total,
                'payment_method' => $isCredit ? 'cash' : $request->payment_method,
                'paid_amount'    => $isCredit ? 0 : $total,
                'balance_used'   => 0,
                'change_amount'  => 0,
            ]);

            foreach ($request->items as $item) {
                $soItem  = SalesOrderItem::findOrFail($item['so_item_id']);
                $product = \App\Models\Product::find($soItem->product_id);

                SaleItem::create([
                    'sale_id'     => $sale->id,
                    'product_id'  => $soItem->product_id,
                    'quantity'    => $item['quantity'],
                    'unit_price'  => $soItem->unit_price,
                    'cost_price'  => $product?->cost_price ?? 0,
                    'total_price' => round($item['quantity'] * (float) $soItem->unit_price, 2),
                ]);

                WarehouseService::out($warehouseId, $soItem->product_id, $item['quantity']);
                $soItem->increment('quantity_delivered', $item['quantity']);
            }

            // Post GL
            (new LedgerPostingService())->postSale($sale->load('items', 'customer'));

            // Update Sales Order status
            $allDelivered = $salesOrder->items()->get()->every(fn($i) => $i->fresh()->isFullyDelivered());
            $salesOrder->update(['status' => $allDelivered ? 'fulfilled' : 'partial']);

            DB::commit();

            return redirect()->route('sales.show', $sale)
                ->with('success', 'تم إنشاء فاتورة البيع — ' . $sale->invoice_number);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'خطأ: ' . $e->getMessage());
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function statusBadge(SalesOrder $o): string
    {
        $info = SalesOrder::$statuses[$o->status] ?? ['label' => $o->status, 'color' => 'secondary'];
        return "<span class='badge bg-{$info['color']}'>{$info['label']}</span>";
    }

    private function actionButtons(SalesOrder $o): string
    {
        /** @var \App\Models\User|null $u */
        $u    = auth()->user();
        $show = '<a href="' . route('sales-orders.show', $o) . '" class="btn btn-sm btn-info btn-action" title="عرض"><i class="bi bi-eye"></i></a>';
        $conf = $conv = '';

        if ($o->status === 'draft' && $u?->can('sales_orders.confirm')) {
            $conf = '<form action="' . route('sales-orders.confirm', $o) . '" method="POST" class="d-inline">' . csrf_field() .
                '<button class="btn btn-sm btn-primary btn-action" title="تأكيد"><i class="bi bi-check2-circle"></i></button></form>';
        }
        if ($o->canConvertToInvoice() && $u?->can('sales_orders.convert')) {
            $conv = '<a href="' . route('sales-orders.convert-form', $o) . '" class="btn btn-sm btn-success btn-action" title="تحويل لفاتورة"><i class="bi bi-arrow-right-circle"></i></a>';
        }

        return '<div class="d-flex gap-1 flex-nowrap">' . $show . $conf . $conv . '</div>';
    }
}
