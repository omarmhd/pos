<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\LedgerPostingService;
use App\Services\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class PurchaseReturnController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:purchases.returns.view')->only(['index', 'show']);
        $this->middleware('can:purchases.returns.create')->only(['create', 'store']);
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = PurchaseReturn::with('supplier', 'user')->select('purchase_returns.*');

            return DataTables::eloquent($query)
                ->addColumn('supplier_name', fn($r) => e($r->supplier?->name ?? '—'))
                ->addColumn('user_name',     fn($r) => e($r->user?->name ?? '—'))
                ->addColumn('total_fmt',     fn($r) => number_format($r->total_amount, 2))
                ->addColumn('method_badge',  fn($r) => $this->methodBadge($r->refund_method))
                ->addColumn('date',          fn($r) => $r->return_date->format('Y-m-d'))
                ->addColumn('action',        fn($r) => $this->actionButtons($r))
                ->rawColumns(['method_badge', 'action'])
                ->make(true);
        }

        return view('purchases.returns.index');
    }

    public function create()
    {
        $suppliers = Supplier::orderBy('name')->get(['id', 'name', 'company']);
        $currency  = Setting::get('currency_symbol', 'ج.م');
        return view('purchases.returns.create', compact('suppliers', 'currency'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id'            => 'required|exists:suppliers,id',
            'return_date'            => 'required|date',
            'refund_method'          => 'required|in:ap_deduction,cash,bank',
            'purchase_id'            => 'nullable|exists:purchases,id',
            'notes'                  => 'nullable|string',
            'items'                  => 'required|array|min:1',
            'items.*.product_id'     => 'required|exists:products,id',
            'items.*.quantity'       => 'required|numeric|min:0.001',
            'items.*.unit_price'     => 'required|numeric|min:0',
        ]);

        // Goods leave the same warehouse they originally came from, or user's default
        $warehouseId = WarehouseService::resolveId(
            $request->purchase_id
                ? (int) Purchase::find($request->purchase_id)?->warehouse_id
                : null
        );

        DB::beginTransaction();
        try {
            $productIds = collect($request->items)->pluck('product_id')->unique()->values()->all();

            // Lock products to prevent concurrent quantity manipulation
            Product::whereIn('id', $productIds)->lockForUpdate()->get();

            // Also lock the stock_levels rows for this warehouse
            \App\Models\StockLevel::where('warehouse_id', $warehouseId)
                ->whereIn('product_id', $productIds)
                ->lockForUpdate()
                ->get();

            $totalAmount = collect($request->items)
                ->sum(fn($i) => $i['quantity'] * $i['unit_price']);

            $ret = PurchaseReturn::create([
                'supplier_id'   => $request->supplier_id,
                'purchase_id'   => $request->purchase_id ?: null,
                'user_id'       => auth()->id(),
                'warehouse_id'  => $warehouseId,
                'return_date'   => $request->return_date,
                'refund_method' => $request->refund_method,
                'total_amount'  => round($totalAmount, 2),
                'notes'         => $request->notes,
            ]);

            foreach ($request->items as $item) {
                PurchaseReturnItem::create([
                    'purchase_return_id' => $ret->id,
                    'product_id'         => $item['product_id'],
                    'quantity'           => $item['quantity'],
                    'unit_price'         => $item['unit_price'],
                    'total_price'        => round($item['quantity'] * $item['unit_price'], 2),
                ]);
                // PurchaseReturnItem::boot() decrements products.quantity
                // Here we also decrement per-warehouse stock_level (row locked above)
                WarehouseService::out($warehouseId, $item['product_id'], $item['quantity']);
            }

            $entry = (new LedgerPostingService())->postPurchaseReturn(
                $ret->load('items', 'supplier')
            );
            $ret->update(['journal_entry_id' => $entry->id, 'is_posted' => true]);

            DB::commit();

            return redirect()->route('purchase-returns.show', $ret)
                ->with('success', 'تم حفظ المرتجع وترحيل القيد — ' . $ret->return_number);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'خطأ: ' . $e->getMessage());
        }
    }

    public function show(PurchaseReturn $purchaseReturn)
    {
        $purchaseReturn->load('supplier', 'user', 'purchase', 'items.product', 'journalEntry.lines.account');
        $currency = Setting::get('currency_symbol', 'ج.م');
        return view('purchases.returns.show', compact('purchaseReturn', 'currency'));
    }

    private function methodBadge(string $method): string
    {
        return match($method) {
            'ap_deduction' => "<span class='badge bg-warning text-dark'>خصم من ذمة المورد</span>",
            'cash'         => "<span class='badge bg-success'>استرداد نقدي</span>",
            'bank'         => "<span class='badge bg-info text-dark'>استرداد بنكي</span>",
            default        => "<span class='badge bg-secondary'>{$method}</span>",
        };
    }

    private function actionButtons(PurchaseReturn $r): string
    {
        return '<a href="' . route('purchase-returns.show', $r) . '" class="btn btn-sm btn-info btn-action" title="عرض"><i class="bi bi-eye"></i></a>';
    }
}
