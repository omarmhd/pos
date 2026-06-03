<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Product;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Models\Setting;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:reports.view');
    }

    public function index()
    {
        return view('reports.index');
    }

    public function sales(Request $request)
    {
        $dateFrom     = $request->input('date_from', now()->startOfMonth());
        $dateTo       = $request->input('date_to',   now()->endOfMonth());
        $branchId     = $this->effectiveBranchId($request);
        $branchLocked = $this->isBranchLocked();
        $branches     = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        $cur          = Setting::get('currency_symbol', 'ج.م');

        $sales = Sale::whereBetween('created_at', [$dateFrom, $dateTo])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('SUM(total_amount) as total')
            ->selectRaw('SUM(discount) as discount')
            ->selectRaw('SUM(tax) as tax')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        $totalSales        = $sales->sum('total');
        $totalTransactions = $sales->sum('count');
        $totalDiscount     = $sales->sum('discount');
        $totalTax          = $sales->sum('tax');

        return view('reports.sales', compact(
            'sales', 'totalSales', 'totalTransactions',
            'totalDiscount', 'totalTax', 'dateFrom', 'dateTo',
            'cur', 'branches', 'branchId', 'branchLocked'
        ));
    }

    public function purchases(Request $request)
    {
        $dateFrom     = $request->input('date_from', now()->startOfMonth());
        $dateTo       = $request->input('date_to',   now()->endOfMonth());
        $branchId     = $this->effectiveBranchId($request);
        $branchLocked = $this->isBranchLocked();
        $branches     = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        $cur          = Setting::get('currency_symbol', 'ج.م');

        $purchases = Purchase::with('supplier')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->get();

        $totalPurchases = $purchases->sum('total_amount');
        $totalPaid      = $purchases->sum('paid_amount');
        $totalRemaining = $totalPurchases - $totalPaid;

        return view('reports.purchases', compact(
            'purchases', 'totalPurchases', 'totalPaid',
            'totalRemaining', 'dateFrom', 'dateTo',
            'cur', 'branches', 'branchId', 'branchLocked'
        ));
    }

    public function profit(Request $request)
    {
        $dateFrom     = $request->input('date_from', now()->startOfMonth());
        $dateTo       = $request->input('date_to',   now()->endOfMonth());
        $branchId     = $this->effectiveBranchId($request);
        $branchLocked = $this->isBranchLocked();
        $branches     = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        $cur          = Setting::get('currency_symbol', 'ج.م');

        $baseQuery = SaleItem::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->whereBetween('sales.created_at', [$dateFrom, $dateTo])
            ->when($branchId, fn($q) => $q->where('sales.branch_id', $branchId));

        $salesData = (clone $baseQuery)->select(
            DB::raw('SUM(sale_items.total_price) as revenue'),
            DB::raw('SUM(sale_items.quantity * sale_items.cost_price) as cost')
        )->first();

        $revenue      = $salesData->revenue ?? 0;
        $cost         = $salesData->cost    ?? 0;
        $profit       = $revenue - $cost;
        $profitMargin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;

        $dailyProfit = (clone $baseQuery)
            ->selectRaw('DATE(sales.created_at) as date')
            ->selectRaw('COUNT(DISTINCT sales.id) as tx_count')
            ->selectRaw('SUM(sale_items.total_price) as revenue')
            ->selectRaw('SUM(sale_items.quantity * sale_items.cost_price) as cost')
            ->selectRaw('(SUM(sale_items.total_price) - SUM(sale_items.quantity * sale_items.cost_price)) as profit')
            ->groupBy(DB::raw('DATE(sales.created_at)'))
            ->orderBy('date', 'desc')
            ->get();

        return view('reports.profit', compact(
            'revenue', 'cost', 'profit', 'profitMargin',
            'dailyProfit', 'dateFrom', 'dateTo', 'cur',
            'branches', 'branchId', 'branchLocked'
        ));
    }

    public function inventory(Request $request)
    {
        $branchId     = $this->effectiveBranchId($request);
        $branchLocked = $this->isBranchLocked();
        $branches     = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        $warehouseId  = $request->filled('warehouse_id') ? (int) $request->warehouse_id : null;
        $warehouses   = \App\Models\Warehouse::where('is_active', true)
                          ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                          ->orderBy('name')->get(['id', 'name', 'branch_id']);
        $cur          = Setting::get('currency_symbol', 'ج.م');

        if ($branchId || $warehouseId) {
            // Per-branch/warehouse: quantities from stock_levels (IAS 2 per-location)
            $products = DB::table('products')
                ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                ->join('stock_levels', 'products.id', '=', 'stock_levels.product_id')
                ->join('warehouses', 'stock_levels.warehouse_id', '=', 'warehouses.id')
                ->when($branchId,    fn($q) => $q->where('warehouses.branch_id', $branchId))
                ->when($warehouseId, fn($q) => $q->where('stock_levels.warehouse_id', $warehouseId))
                ->selectRaw('products.id, products.name, products.barcode,
                    products.selling_price, products.cost_price, products.min_quantity,
                    categories.name as category_name,
                    SUM(stock_levels.quantity) as quantity,
                    SUM(stock_levels.quantity * products.cost_price) as inventory_value,
                    SUM(stock_levels.quantity * (products.selling_price - products.cost_price)) as potential_profit')
                ->groupBy('products.id', 'products.name', 'products.barcode',
                          'products.selling_price', 'products.cost_price',
                          'products.min_quantity', 'categories.name')
                ->get();

            $lowStockCount = $products->filter(fn($p) => $p->quantity <= $p->min_quantity)->count();
        } else {
            // Global view: use products.quantity (SUM cache)
            $products = Product::with('category')
                ->select('products.*')
                ->selectRaw('(selling_price - cost_price) * quantity as potential_profit')
                ->selectRaw('cost_price * quantity as inventory_value')
                ->get();

            $lowStockCount = $products->filter(fn($p) => $p->quantity <= $p->min_quantity)->count();
        }

        $totalValue           = $products->sum('inventory_value');
        $totalPotentialProfit = $products->sum('potential_profit');

        return view('reports.inventory', compact(
            'products', 'totalValue', 'totalPotentialProfit', 'lowStockCount', 'cur',
            'branches', 'branchId', 'branchLocked', 'warehouses', 'warehouseId'
        ));
    }

    public function topProducts(Request $request)
    {
        $dateFrom     = $request->input('date_from', now()->startOfMonth());
        $dateTo       = $request->input('date_to',   now()->endOfMonth());
        $branchId     = $this->effectiveBranchId($request);
        $branchLocked = $this->isBranchLocked();
        $branches     = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        $cur          = Setting::get('currency_symbol', 'ج.م');

        $products = Product::join('sale_items', 'products.id', '=', 'sale_items.product_id')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->whereBetween('sales.created_at', [$dateFrom, $dateTo])
            ->when($branchId, fn($q) => $q->where('sales.branch_id', $branchId))
            ->select(
                'products.*',
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw('SUM(sale_items.total_price) as total_revenue'),
                DB::raw('COUNT(DISTINCT sales.id) as times_sold')
            )
            ->groupBy('products.id')
            ->orderBy('total_revenue', 'desc')
            ->take(20)
            ->get();

        return view('reports.top-products', compact(
            'products', 'dateFrom', 'dateTo', 'cur',
            'branches', 'branchId', 'branchLocked'
        ));
    }

    public function apAging()
    {
        $branchId = $this->effectiveBranchId(request());
        $cur      = Setting::get('currency_symbol', 'ج.م');

        $makeRow = function ($invoice, $reference, $vendorName, $vendorId, $type) {
            $remaining = round((float) $invoice->total_amount - (float) $invoice->paid_amount, 2);
            if ($remaining < 0.01) {
                return null;
            }
            $date      = method_exists($invoice, 'invoice_date')
                ? ($invoice->invoice_date ?? $invoice->created_at)
                : $invoice->created_at;
            if ($date instanceof \Carbon\Carbon) {
                $dateStr = $date->toDateString();
            } else {
                $dateStr = \Carbon\Carbon::parse($date)->toDateString();
                $date    = \Carbon\Carbon::parse($date);
            }
            $ageInDays = (int) now()->diffInDays($date, false) * -1;

            return [
                'type'         => $type,
                'vendor'       => $vendorName,
                'vendor_id'    => $vendorId,
                'invoice'      => $reference,
                'invoice_date' => $dateStr,
                'total'        => (float) $invoice->total_amount,
                'paid'         => (float) $invoice->paid_amount,
                'outstanding'  => $remaining,
                'age_days'     => $ageInDays,
                'bucket'       => match (true) {
                    $ageInDays <= 30 => 'current',
                    $ageInDays <= 60 => '31_60',
                    $ageInDays <= 90 => '61_90',
                    default          => 'over_90',
                },
            ];
        };

        // 1. Purchase invoices (بضاعة)
        $purchaseRows = Purchase::with('supplier')
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->get()
            ->map(fn($p) => $makeRow(
                $p, $p->invoice_number,
                $p->supplier->name ?? '—', $p->supplier_id,
                'purchase'
            ))
            ->filter();

        // 2. Expense invoices (مصروفات) — THE NEW ADDITION
        $expenseRows = \App\Models\ExpenseInvoice::whereIn('payment_status', ['unpaid', 'partial'])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->get()
            ->map(fn($e) => $makeRow(
                $e, $e->invoice_number,
                $e->vendor_name, null,
                'expense'
            ))
            ->filter();

        $rows = $purchaseRows->concat($expenseRows)
            ->sortByDesc('age_days')
            ->values()
            ->all();

        $buckets = [
            'current' => collect($rows)->where('bucket', 'current')->sum('outstanding'),
            '31_60'   => collect($rows)->where('bucket', '31_60')->sum('outstanding'),
            '61_90'   => collect($rows)->where('bucket', '61_90')->sum('outstanding'),
            'over_90' => collect($rows)->where('bucket', 'over_90')->sum('outstanding'),
        ];
        $totalOutstanding = array_sum($buckets);

        return view('reports.ap_aging', compact('rows', 'buckets', 'totalOutstanding', 'cur'));
    }

    public function arAging(Request $request)
    {
        $branchId     = $this->effectiveBranchId($request);
        $branchLocked = $this->isBranchLocked();
        $branches     = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        $cur          = Setting::get('currency_symbol', 'ج.م');

        // Single query: credit sales + pre-aggregated payments — no PHP-side looping over payments
        $data = DB::table('sales')
            ->join('customers', 'sales.customer_id', '=', 'customers.id')
            ->leftJoin(
                DB::raw('(SELECT sale_id, COALESCE(SUM(amount), 0) as paid_sum FROM customer_payments GROUP BY sale_id) cp'),
                'cp.sale_id', '=', 'sales.id'
            )
            ->where('sales.is_credit', true)
            ->when($branchId, fn($q) => $q->where('sales.branch_id', $branchId))
            ->select(
                'customers.id as customer_id',
                'customers.name as customer',
                'sales.invoice_number as invoice',
                'sales.created_at as invoice_date',
                'sales.total_amount as total',
                DB::raw('COALESCE(cp.paid_sum, 0) as paid'),
                DB::raw('sales.total_amount - COALESCE(cp.paid_sum, 0) as outstanding')
            )
            ->having('outstanding', '>', 0.01)
            ->orderBy('sales.created_at')
            ->get();

        $rows = $data->map(function ($row) {
            $ageInDays = (int) now()->diffInDays(Carbon::parse($row->invoice_date), false) * -1;
            return [
                'customer'     => $row->customer,
                'customer_id'  => $row->customer_id,
                'invoice'      => $row->invoice,
                'invoice_date' => Carbon::parse($row->invoice_date)->toDateString(),
                'total'        => (float) $row->total,
                'paid'         => (float) $row->paid,
                'outstanding'  => (float) $row->outstanding,
                'age_days'     => $ageInDays,
                'bucket'       => match (true) {
                    $ageInDays <= 30 => 'current',
                    $ageInDays <= 60 => '31_60',
                    $ageInDays <= 90 => '61_90',
                    default          => 'over_90',
                },
            ];
        })->sortByDesc('age_days')->values()->all();

        $buckets = [
            'current' => collect($rows)->where('bucket', 'current')->sum('outstanding'),
            '31_60'   => collect($rows)->where('bucket', '31_60')->sum('outstanding'),
            '61_90'   => collect($rows)->where('bucket', '61_90')->sum('outstanding'),
            'over_90' => collect($rows)->where('bucket', 'over_90')->sum('outstanding'),
        ];
        $totalOutstanding = array_sum($buckets);

        return view('reports.ar_aging', compact(
            'rows', 'buckets', 'totalOutstanding', 'cur',
            'branches', 'branchId', 'branchLocked'
        ));
    }
}
