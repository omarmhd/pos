<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Services\LedgerPostingService;
use App\Services\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class SaleReturnController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:sales.returns.view')->only(['index', 'show']);
        $this->middleware('can:sales.returns.create')->only(['create', 'store']);
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = SaleReturn::with('customer', 'user')->select('sale_returns.*');

            return DataTables::eloquent($query)
                ->addColumn('customer_name', fn($r) => e($r->customer?->name ?? '—'))
                ->addColumn('user_name',     fn($r) => e($r->user?->name ?? '—'))
                ->addColumn('total_fmt',     fn($r) => number_format($r->total_amount, 2))
                ->addColumn('method_badge',  fn($r) => $this->methodBadge($r->refund_method))
                ->addColumn('date',          fn($r) => $r->return_date->format('Y-m-d'))
                ->addColumn('action',        fn($r) => $this->actionButtons($r))
                ->rawColumns(['method_badge', 'action'])
                ->make(true);
        }

        return view('sales.returns.index');
    }

    public function create()
    {
        $customers = Customer::orderBy('name')->get(['id', 'name', 'phone']);
        $currency  = Setting::get('currency_symbol', 'ج.م');
        return view('sales.returns.create', compact('customers', 'currency'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'return_date'             => 'required|date',
            'refund_method'           => 'required|in:cash,bank,credit_note',
            'customer_id'             => 'nullable|exists:customers,id',
            'sale_id'                 => 'nullable|exists:sales,id',
            'warehouse_id'            => 'nullable|exists:warehouses,id',
            'notes'                   => 'nullable|string',
            'items'                   => 'required|array|min:1',
            'items.*.product_id'      => 'required|exists:products,id',
            'items.*.quantity'        => 'required|numeric|min:0.001',
            'items.*.unit_price'      => 'required|numeric|min:0',
        ]);

        if ($request->refund_method === 'credit_note' && !$request->customer_id) {
            return back()->withInput()
                ->with('error', 'إشعار الدائن يتطلب تحديد عميل');
        }

        // Returned goods go back to the same warehouse the original sale came from,
        // or fall back to the user's branch warehouse / system default.
        $warehouseId = $request->warehouse_id
            ? (int) $request->warehouse_id
            : WarehouseService::getForUser(auth()->user())->id;

        DB::beginTransaction();
        try {
            // Lock products rows to prevent concurrent quantity manipulation
            $productIds = collect($request->items)->pluck('product_id')->unique()->values()->all();
            $products = Product::whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');

            // Snapshot cost prices from the original sale (if linked), so COGS
            // reversal uses the cost recorded AT THE TIME OF SALE rather than
            // the product's current AVCO cost (which may have moved since).
            $originalCostByProduct = $request->sale_id
                ? \App\Models\SaleItem::where('sale_id', $request->sale_id)
                    ->whereIn('product_id', $productIds)
                    ->get()
                    ->keyBy('product_id')
                    ->map(fn($i) => $i->cost_price)
                : collect();

            $totalAmount = 0;
            foreach ($request->items as $item) {
                $totalAmount += $item['quantity'] * $item['unit_price'];
            }

            $ret = SaleReturn::create([
                'sale_id'       => $request->sale_id ?: null,
                'customer_id'   => $request->customer_id ?: null,
                'user_id'       => auth()->id(),
                'warehouse_id'  => $warehouseId,
                'return_date'   => $request->return_date,
                'refund_method' => $request->refund_method,
                'total_amount'  => round($totalAmount, 2),
                'notes'         => $request->notes,
            ]);

            foreach ($request->items as $item) {
                $product = $products->get($item['product_id']) ?? Product::findOrFail($item['product_id']);
                SaleReturnItem::create([
                    'sale_return_id' => $ret->id,
                    'product_id'     => $item['product_id'],
                    'quantity'       => $item['quantity'],
                    'unit_price'     => $item['unit_price'],
                    // Use the cost recorded on the original sale line when available
                    // (AVCO may have moved since the sale), otherwise fall back to
                    // the product's current cost price.
                    'cost_price'     => $originalCostByProduct->get($item['product_id'], $product->cost_price),
                    'total_price'    => round($item['quantity'] * $item['unit_price'], 2),
                ]);
                // SaleReturnItem::boot() increments products.quantity
                // Here we also increment per-warehouse stock_level
                WarehouseService::in($warehouseId, $item['product_id'], $item['quantity']);
            }

            $entry = (new LedgerPostingService())->postSaleReturn($ret->load('items.product', 'customer'));
            $ret->update(['journal_entry_id' => $entry->id, 'is_posted' => true]);

            DB::commit();

            return redirect()->route('sale-returns.show', $ret)
                ->with('success', 'تم حفظ المرتجع وترحيل القيد — ' . $ret->return_number);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'خطأ: ' . $e->getMessage());
        }
    }

    public function show(SaleReturn $saleReturn)
    {
        $saleReturn->load('customer', 'user', 'sale', 'items.product', 'journalEntry.lines.account');
        $currency = Setting::get('currency_symbol', 'ج.م');
        return view('sales.returns.show', compact('saleReturn', 'currency'));
    }

    // ── private helpers ──────────────────────────────────────────────────────

    private function methodBadge(string $method): string
    {
        return match($method) {
            'cash'        => "<span class='badge bg-success'>نقدي</span>",
            'bank'        => "<span class='badge bg-info text-dark'>بنكي</span>",
            'credit_note' => "<span class='badge bg-warning text-dark'>إشعار دائن</span>",
            default       => "<span class='badge bg-secondary'>{$method}</span>",
        };
    }

    private function actionButtons(SaleReturn $r): string
    {
        return '<a href="' . route('sale-returns.show', $r) . '" class="btn btn-sm btn-info btn-action" title="عرض"><i class="bi bi-eye"></i></a>';
    }
}
