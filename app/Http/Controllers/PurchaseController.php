<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\LedgerPostingService;
use App\Services\PdfService;
use App\Services\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class PurchaseController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:purchases.view')->only(['index', 'show', 'pdf']);
        $this->middleware('can:purchases.create')->only(['create', 'store', 'quickCreateProduct']);
        $this->middleware('can:purchases.delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $branchId = $this->effectiveBranchId($request);
            $query = Purchase::with('supplier', 'user')
                ->select('purchases.*')
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId));

            return DataTables::eloquent($query)
                ->addColumn('supplier_name', fn($p) => e($p->supplier?->name ?? '—'))
                ->addColumn('user_name',     fn($p) => e($p->user?->name ?? '—'))
                ->addColumn('total_fmt',     fn($p) => number_format($p->total_amount, 2))
                ->addColumn('paid_fmt',      fn($p) => number_format($p->paid_amount, 2))
                ->addColumn('remaining_fmt', fn($p) => number_format($p->remainingAmount(), 2))
                ->addColumn('status_badge',  fn($p) => $this->statusBadge($p->payment_status))
                ->addColumn('date',          fn($p) => $p->created_at->format('Y-m-d'))
                ->addColumn('action',        fn($p) => $this->actionButtons($p))
                ->filterColumn('supplier_name', fn($q, $k) =>
                    $q->whereHas('supplier', fn($s) => $s->where('name', 'like', "%$k%")))
                ->filterColumn('user_name', fn($q, $k) =>
                    $q->whereHas('user', fn($u) => $u->where('name', 'like', "%$k%")))
                ->rawColumns(['status_badge', 'action'])
                ->make(true);
        }

        return view('purchases.index');
    }

    public function create()
    {
        $suppliers  = Supplier::orderBy('name')->get();
        $currency   = Setting::get('currency_symbol', 'ج.م');
        $warehouses = Warehouse::where('is_active', true)
            ->with('branch:id,name')
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'branch_id', 'is_default']);
        $defaultWarehouseId = WarehouseService::getDefault()->id;

        return view('purchases.create', compact('suppliers', 'currency', 'warehouses', 'defaultWarehouseId'));
    }

    public function searchProducts(Request $request)
    {
        $q = trim($request->get('q', ''));

        $products = Product::when($q, fn($query) =>
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('barcode', 'like', "%{$q}%")
            )
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name', 'barcode', 'cost_price']);

        return response()->json($products->map(fn($p) => [
            'id'            => $p->id,
            'text'          => $p->name . ($p->barcode ? ' — ' . $p->barcode : ''),
            'name'          => $p->name,
            'cost_price'    => (float) $p->cost_price,
            'selling_price' => (float) $p->selling_price,
        ]));
    }

    public function quickCreateProduct(Request $request)
    {
        $request->validate([
            'name'          => 'required|string|max:255',
            'barcode'       => 'nullable|string|max:100|unique:products,barcode',
            'cost_price'    => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'unit'          => 'nullable|string|max:50',
        ]);

        $product = Product::create([
            'name'          => $request->name,
            'barcode'       => $request->barcode ?: null,
            'cost_price'    => $request->cost_price,
            'selling_price' => $request->selling_price,
            'unit'          => $request->unit ?: 'قطعة',
            'quantity'      => 0,
            'min_quantity'  => 5,
        ]);

        return response()->json([
            'id'         => $product->id,
            'text'       => $product->name . ($product->barcode ? ' — ' . $product->barcode : ''),
            'name'       => $product->name,
            'cost_price' => (float) $product->cost_price,
        ], 201);
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id'              => 'required|exists:suppliers,id',
            'supplier_invoice_number'  => 'nullable|string|max:100',
            'warehouse_id'             => 'nullable|exists:warehouses,id',
            'payment_status'           => 'required|in:paid,partial,unpaid',
            'paid_amount'              => 'required|numeric|min:0',
            'notes'                    => 'nullable|string',
            'items'                    => 'required|array|min:1',
            'items.*.product_id'       => 'required|exists:products,id',
            'items.*.quantity'         => 'required|numeric|min:0.001',
            'items.*.unit_price'       => 'required|numeric|min:0',
            'items.*.lot_number'       => 'nullable|string|max:100',
            'items.*.expiry_date'      => 'nullable|date',
        ]);

        $warehouseId = WarehouseService::resolveId($request->input('warehouse_id'));

        DB::beginTransaction();
        try {
            // Lock products to prevent concurrent stock manipulation
            $productIds = collect($request->items)->pluck('product_id')->unique()->values()->all();
            Product::whereIn('id', $productIds)->lockForUpdate()->get();

            $totalAmount = 0;
            foreach ($request->items as $item) {
                $totalAmount += $item['quantity'] * $item['unit_price'];
            }

            /** @var Purchase $purchase */
            $purchase = Purchase::create([
                'supplier_id'             => $request->supplier_id,
                'supplier_invoice_number' => $request->supplier_invoice_number ?: null,
                'user_id'                 => auth()->id(),
                'warehouse_id'            => $warehouseId,
                'total_amount'            => $totalAmount,
                'payment_status'          => $request->payment_status,
                'paid_amount'             => $request->paid_amount,
                'notes'                   => $request->notes,
            ]);

            foreach ($request->items as $item) {
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id'  => $item['product_id'],
                    'quantity'    => $item['quantity'],
                    'unit_price'  => $item['unit_price'],
                    'total_price' => $item['quantity'] * $item['unit_price'],
                    'lot_number'  => $item['lot_number']  ?? null,
                    'expiry_date' => $item['expiry_date'] ?? null,
                ]);
                // Update per-warehouse stock level (products.quantity handled by model boot)
                WarehouseService::in($warehouseId, $item['product_id'], $item['quantity']);
            }

            // Post to GL — inside the transaction so it rolls back on failure
            (new LedgerPostingService())->postPurchase($purchase->load('supplier'));

            DB::commit();

            return redirect()->route('purchases.show', $purchase)
                ->with('success', 'تم إضافة فاتورة الشراء بنجاح');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'حدث خطأ أثناء حفظ الفاتورة: ' . $e->getMessage());
        }
    }

    public function show(Purchase $purchase)
    {
        $purchase->load('supplier', 'user', 'items.product');
        $currency = Setting::get('currency_symbol', 'ج.م');
        return view('purchases.show', compact('purchase', 'currency'));
    }

    public function pdf(Purchase $purchase): \Illuminate\Http\Response
    {
        $purchase->load('supplier', 'user', 'items.product');
        $currency = Setting::get('currency_symbol', 'ج.م');
        return PdfService::arabic('pdf.purchase', compact('purchase', 'currency'))
            ->download('purchase-' . $purchase->invoice_number . '.pdf');
    }

    public function destroy(Purchase $purchase)
    {
        if ($purchase->is_posted) {
            return redirect()->route('purchases.index')
                ->with('error', 'لا يمكن حذف فاتورة الشراء بعد ترحيلها. استخدم قيد تصحيح.');
        }

        DB::beginTransaction();
        try {
            // PurchaseItem::deleting() fires on cascade:
            //   → creates reversal StockMovement
            //   → calls WarehouseService::out() → updates stock_levels + products.quantity
            // No manual quantity update needed here.
            $purchase->delete();

            \App\Models\AuditLog::create([
                'user_id'        => auth()->id(),
                'auditable_type' => Purchase::class,
                'auditable_id'   => $purchase->id,
                'action'         => 'deleted',
                'ip_address'     => request()->ip(),
            ]);

            DB::commit();

            return redirect()->route('purchases.index')
                ->with('success', 'تم حذف فاتورة الشراء بنجاح');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'حدث خطأ أثناء حذف الفاتورة');
        }
    }

    // ── private helpers ──────────────────────────────────────────────────────

    private function statusBadge(string $status): string
    {
        $map = [
            'paid'    => ['success', 'مدفوع'],
            'partial' => ['warning', 'جزئي'],
            'unpaid'  => ['danger',  'غير مدفوع'],
        ];
        [$color, $label] = $map[$status] ?? ['secondary', $status];
        return "<span class='badge bg-{$color}'>{$label}</span>";
    }

    private function actionButtons(Purchase $p): string
    {
        $show = '<a href="'.route('purchases.show', $p).'" class="btn btn-sm btn-info btn-action" title="عرض"><i class="bi bi-eye"></i></a>';
        $pdf  = '<a href="'.route('purchases.pdf', $p).'" class="btn btn-sm btn-outline-danger btn-action" title="PDF" target="_blank"><i class="bi bi-file-earmark-pdf"></i></a>';
        $del  = '';
        /** @var \App\Models\User $user */
        $user = auth()->user();
        if ($user->can('purchases.delete') && !$p->is_posted) {
            $token = csrf_token();
            $del = '<form action="'.route('purchases.destroy', $p).'" method="POST" class="d-inline"'
                 . ' onsubmit="return confirm(\'هل أنت متأكد؟ سيتم استرجاع المنتجات من المخزون.\')">'
                 . '<input type="hidden" name="_token" value="'.$token.'">'
                 . '<input type="hidden" name="_method" value="DELETE">'
                 . '<button class="btn btn-sm btn-outline-secondary btn-action" title="حذف"><i class="bi bi-trash"></i></button></form>';
        }
        return '<div class="d-flex gap-1 flex-nowrap">'.$show.$pdf.$del.'</div>';
    }
}
