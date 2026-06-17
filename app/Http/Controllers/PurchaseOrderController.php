<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class PurchaseOrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:purchase_orders.view')->only(['index', 'show', 'pdf']);
        $this->middleware('can:purchase_orders.create')->only(['create', 'store']);
        $this->middleware('can:purchase_orders.send')->only(['send']);
        $this->middleware('can:purchase_orders.cancel')->only(['cancel']);
        $this->middleware('can:purchase_orders.convert')->only(['convertForm', 'convert']);
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $branchId = $this->effectiveBranchId($request);
            $query = PurchaseOrder::with('supplier', 'user')
                ->select('purchase_orders.*')
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId));

            return DataTables::eloquent($query)
                ->addColumn('supplier_name', fn($p) => e($p->supplier?->name ?? '—'))
                ->addColumn('user_name',     fn($p) => e($p->user?->name ?? '—'))
                ->addColumn('total_fmt',     fn($p) => number_format($p->total_amount, 2))
                ->addColumn('status_badge',  fn($p) => $this->statusBadge($p))
                ->addColumn('date',          fn($p) => $p->order_date->format('Y-m-d'))
                ->editColumn('expected_delivery_date', fn($p) => $p->expected_delivery_date?->format('Y-m-d') ?? '—')
                ->addColumn('action',        fn($p) => $this->actionButtons($p))
                ->rawColumns(['status_badge', 'action'])
                ->make(true);
        }

        $branches     = \App\Models\Branch::where('is_active', true)->orderBy('name')->get(['id','name','code']);
        $branchId     = $this->effectiveBranchId($request);
        $branchLocked = $this->isBranchLocked();
        $currency     = Setting::get('currency_symbol', 'ج.م');

        return view('purchase-orders.index', compact('branches', 'branchId', 'branchLocked', 'currency'));
    }

    public function create()
    {
        $suppliers  = Supplier::orderBy('name')->get(['id', 'name', 'company']);
        $warehouses = Warehouse::where('is_active', true)
            ->with('branch:id,name')
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'branch_id', 'is_default']);
        $defaultWarehouseId = WarehouseService::getDefault()->id;
        $currency   = Setting::get('currency_symbol', 'ج.م');
        $branches   = \App\Models\Branch::where('is_active', true)->orderBy('name')->get(['id','name','code']);

        return view('purchase-orders.create', compact('suppliers', 'warehouses', 'defaultWarehouseId', 'currency', 'branches'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id'             => 'required|exists:suppliers,id',
            'order_date'              => 'required|date',
            'expected_delivery_date'  => 'nullable|date|after_or_equal:order_date',
            'warehouse_id'            => 'nullable|exists:warehouses,id',
            'terms'                   => 'nullable|string',
            'notes'                   => 'nullable|string',
            'items'                   => 'required|array|min:1',
            'items.*.product_id'      => 'required|exists:products,id',
            'items.*.quantity_ordered'=> 'required|numeric|min:0.001',
            'items.*.unit_price'      => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $warehouseId = WarehouseService::resolveId($request->input('warehouse_id'));

            $totalAmount = collect($request->items)
                ->sum(fn($i) => $i['quantity_ordered'] * $i['unit_price']);

            $po = PurchaseOrder::create([
                'supplier_id'            => $request->supplier_id,
                'user_id'                => auth()->id(),
                'branch_id'              => auth()->user()->branch_id ?? Setting::get('default_branch_id'),
                'warehouse_id'           => $warehouseId,
                'order_date'             => $request->order_date,
                'expected_delivery_date' => $request->expected_delivery_date ?: null,
                'status'                 => 'draft',
                'total_amount'           => round($totalAmount, 2),
                'terms'                  => $request->terms,
                'notes'                  => $request->notes,
            ]);

            foreach ($request->items as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id'        => $item['product_id'],
                    'quantity_ordered'  => $item['quantity_ordered'],
                    'unit_price'        => $item['unit_price'],
                    'total_price'       => round($item['quantity_ordered'] * $item['unit_price'], 2),
                    'notes'             => $item['notes'] ?? null,
                ]);
            }

            DB::commit();

            return redirect()->route('purchase-orders.show', $po)
                ->with('success', 'تم إنشاء أمر الشراء — ' . $po->po_number);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'خطأ: ' . $e->getMessage());
        }
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load('supplier', 'user', 'branch', 'warehouse', 'items.product', 'invoices');
        $currency = Setting::get('currency_symbol', 'ج.م');
        return view('purchase-orders.show', compact('purchaseOrder', 'currency'));
    }

    /**
     * Generate a PDF for the purchase order.
     * Streams inline by default (for browser preview / printing);
     * pass ?download=1 to force a file download.
     */
    public function pdf(Request $request, PurchaseOrder $purchaseOrder): \Illuminate\Http\Response
    {
        $purchaseOrder->load('supplier', 'user', 'branch', 'warehouse', 'items.product');
        $currency = Setting::get('currency_symbol', 'ج.م');

        $pdf      = \App\Services\PdfService::arabic('pdf.purchase_order', compact('purchaseOrder', 'currency'));
        $filename = 'purchase-order-' . $purchaseOrder->po_number . '.pdf';

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }

    /** Mark PO as sent to supplier */
    public function send(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== 'draft') {
            return back()->with('error', 'يمكن إرسال أوامر الشراء بحالة "مسودة" فقط');
        }

        $purchaseOrder->update([
            'status'  => 'sent',
            'sent_at' => now()->toDateString(),
        ]);

        return back()->with('success', 'تم تغيير حالة أمر الشراء إلى "مُرسَل للمورد"');
    }

    /** Cancel a PO */
    public function cancel(PurchaseOrder $purchaseOrder)
    {
        if (in_array($purchaseOrder->status, ['received', 'cancelled'])) {
            return back()->with('error', 'لا يمكن إلغاء هذا الأمر');
        }

        $purchaseOrder->update(['status' => 'cancelled']);
        return back()->with('success', 'تم إلغاء أمر الشراء');
    }

    /**
     * Show the "convert to purchase invoice" form.
     * Pre-fills the invoice form with PO data + remaining quantities.
     */
    public function convertForm(PurchaseOrder $purchaseOrder)
    {
        if (!$purchaseOrder->canConvertToInvoice()) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->with('error', 'لا يمكن تحويل هذا الأمر إلى فاتورة في حالته الحالية');
        }

        $purchaseOrder->load('supplier', 'items.product', 'warehouse');
        $currency = Setting::get('currency_symbol', 'ج.م');

        return view('purchase-orders.convert', compact('purchaseOrder', 'currency'));
    }

    /**
     * Create a purchase invoice from a PO.
     * Updates PO item quantities and status.
     */
    public function convert(Request $request, PurchaseOrder $purchaseOrder)
    {
        $request->validate([
            'payment_status'           => 'required|in:paid,partial,unpaid',
            'paid_amount'              => 'required|numeric|min:0',
            'supplier_invoice_number'  => 'nullable|string|max:100',
            'notes'                    => 'nullable|string',
            'items'                    => 'required|array|min:1',
            'items.*.po_item_id'       => 'required|exists:purchase_order_items,id',
            'items.*.quantity'         => 'required|numeric|min:0.001',
            'items.*.unit_price'       => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $totalAmount = collect($request->items)
                ->sum(fn($i) => $i['quantity'] * $i['unit_price']);

            // Create the purchase invoice
            $purchase = Purchase::create([
                'supplier_id'             => $purchaseOrder->supplier_id,
                'purchase_order_id'       => $purchaseOrder->id,
                'supplier_invoice_number' => $request->supplier_invoice_number ?: null,
                'user_id'                 => auth()->id(),
                'warehouse_id'            => $purchaseOrder->warehouse_id,
                'branch_id'               => $purchaseOrder->branch_id,
                'total_amount'            => round($totalAmount, 2),
                'payment_status'          => $request->payment_status,
                'paid_amount'             => $request->paid_amount,
                'notes'                   => $request->notes,
            ]);

            foreach ($request->items as $item) {
                $poItem = PurchaseOrderItem::findOrFail($item['po_item_id']);

                \App\Models\PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id'  => $poItem->product_id,
                    'quantity'    => $item['quantity'],
                    'unit_price'  => $item['unit_price'],
                    'total_price' => round($item['quantity'] * $item['unit_price'], 2),
                ]);

                WarehouseService::in($purchaseOrder->warehouse_id, $poItem->product_id, $item['quantity']);

                // Update received quantity on PO item
                $poItem->increment('quantity_received', $item['quantity']);
            }

            // Post GL
            (new \App\Services\LedgerPostingService())->postPurchase($purchase->load('supplier'));

            // Update PO status
            $allReceived = $purchaseOrder->items()->get()->every(fn($i) => $i->fresh()->isFullyReceived());
            $purchaseOrder->update(['status' => $allReceived ? 'received' : 'partial']);

            DB::commit();

            return redirect()->route('purchases.show', $purchase)
                ->with('success', 'تم إنشاء فاتورة الشراء من أمر الشراء — ' . $purchase->invoice_number);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'خطأ: ' . $e->getMessage());
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function statusBadge(PurchaseOrder $po): string
    {
        $info  = PurchaseOrder::$statuses[$po->status] ?? ['label' => $po->status, 'color' => 'secondary'];
        return "<span class='badge bg-{$info['color']}'>{$info['label']}</span>";
    }

    private function actionButtons(PurchaseOrder $po): string
    {
        /** @var \App\Models\User|null $u */
        $u    = auth()->user();
        $show = '<a href="' . route('purchase-orders.show', $po) . '" class="btn btn-sm btn-info btn-action" title="عرض"><i class="bi bi-eye"></i></a>';
        $pdf  = '<a href="' . route('purchase-orders.pdf', $po) . '" target="_blank" class="btn btn-sm btn-outline-danger btn-action" title="طباعة / PDF"><i class="bi bi-file-earmark-pdf"></i></a>';
        $conv = '';
        $send = '';

        if ($po->status === 'draft' && $u?->can('purchase_orders.send')) {
            $send = '<form action="' . route('purchase-orders.send', $po) . '" method="POST" class="d-inline">
                        ' . csrf_field() . '
                        <button class="btn btn-sm btn-primary btn-action" title="إرسال للمورد"><i class="bi bi-send"></i></button>
                     </form>';
        }

        if ($po->canConvertToInvoice() && $u?->can('purchase_orders.convert')) {
            $conv = '<a href="' . route('purchase-orders.convert-form', $po) . '" class="btn btn-sm btn-success btn-action" title="تحويل لفاتورة شراء"><i class="bi bi-arrow-right-circle"></i></a>';
        }

        return '<div class="d-flex gap-1 flex-nowrap">' . $show . $pdf . $send . $conv . '</div>';
    }
}
