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

    public function apAging(Request $request)
    {
        $branchId = $this->effectiveBranchId($request);
        $cur      = Setting::get('currency_symbol', 'ج.م');

        $makeRow = function ($invoice, $reference, $vendorName, $vendorId, $type) {
            $remaining = method_exists($invoice, 'remainingAmount')
                ? round((float) $invoice->remainingAmount(), 2)
                : round((float) $invoice->total_amount - (float) $invoice->paid_amount, 2);
                
            if ($remaining < 0.01) {
                return null;
            }
            // invoice_date حقل (وليس دالة) — يُستخدم إن وُجد وإلا created_at
            $rawDate = $invoice->invoice_date ?? $invoice->created_at ?? now();
            $date    = $rawDate instanceof \Carbon\Carbon ? $rawDate : \Carbon\Carbon::parse($rawDate);
            $dateStr = $date->toDateString();
            // عمر موجب بالأيام، آمن عبر إصدارات Carbon
            $ageInDays = (int) abs($date->copy()->startOfDay()->diffInDays(now()->startOfDay()));

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

        // 1. Purchase invoices
        $purchaseRows = Purchase::with('supplier')
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->get()
            ->map(fn($p) => $makeRow(
                $p, $p->invoice_number,
                $p->supplier?->name ?? '—', $p->supplier_id,
                'purchase'
            ))
            ->filter();

        // 2. Expense invoices
        $expenseRows = \App\Models\ExpenseInvoice::whereIn('payment_status', ['unpaid', 'partial'])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->get()
            ->map(fn($e) => $makeRow(
                $e, $e->invoice_number,
                $e->vendor_name, null,
                'expense'
            ))
            ->filter();

        $rows = $purchaseRows->concat($expenseRows)->sortByDesc('age_days')->values();

        if ($request->ajax()) {
            return \Yajra\DataTables\Facades\DataTables::collection($rows)
                ->addColumn('vendor_col', function ($r) {
                    if (isset($r['type']) && $r['type'] === 'expense') {
                        return '<span class="badge bg-danger me-1 small">مصروف</span>' . e($r['vendor'] ?? '—');
                    } elseif (isset($r['vendor_id']) && $r['vendor_id']) {
                        return '<span class="badge bg-primary me-1 small">شراء</span>' . 
                               '<a href="' . route('suppliers.show', $r['vendor_id']) . '" class="text-decoration-none">' . e($r['vendor'] ?? '—') . '</a>';
                    }
                    return e($r['vendor'] ?? '—');
                })
                ->addColumn('invoice_col', fn($r) => '<span class="badge bg-light text-dark">' . e($r['invoice']) . '</span>')
                ->addColumn('total_fmt', fn($r) => number_format($r['total'], 2))
                ->addColumn('paid_fmt', fn($r) => '<span class="text-success">' . number_format($r['paid'], 2) . '</span>')
                ->addColumn('outstanding_fmt', function($r) {
                    $cls = $r['age_days'] > 60 ? 'text-danger' : '';
                    return '<span class="fw-bold ' . $cls . '">' . number_format($r['outstanding'], 2) . '</span>';
                })
                ->addColumn('age_fmt', function($r) {
                    $cls = $r['age_days'] > 90 ? 'text-danger fw-bold' : '';
                    return '<span class="' . $cls . '">' . $r['age_days'] . '</span>';
                })
                ->addColumn('bucket_col', function($r) {
                    return match($r['bucket']) {
                        'current' => '<span class="badge bg-primary">جاري</span>',
                        '31_60'   => '<span class="badge bg-warning text-dark">31–60</span>',
                        '61_90'   => '<span class="badge" style="background:#fd7e14;color:white">61–90</span>',
                        default   => '<span class="badge bg-danger">+90 يوم</span>',
                    };
                })
                ->rawColumns(['vendor_col', 'invoice_col', 'paid_fmt', 'outstanding_fmt', 'age_fmt', 'bucket_col'])
                ->make(true);
        }

        $buckets = [
            'current' => $rows->where('bucket', 'current')->sum('outstanding'),
            '31_60'   => $rows->where('bucket', '31_60')->sum('outstanding'),
            '61_90'   => $rows->where('bucket', '61_90')->sum('outstanding'),
            'over_90' => $rows->where('bucket', 'over_90')->sum('outstanding'),
        ];
        $totalOutstanding = array_sum($buckets);

        return view('reports.ap_aging', compact('buckets', 'totalOutstanding', 'cur'));
    }

    public function arAging(Request $request)
    {
        $branchId     = $this->effectiveBranchId($request);
        $branchLocked = $this->isBranchLocked();
        $branches     = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        $cur          = Setting::get('currency_symbol', 'ج.م');

        $sales = Sale::with(['customer', 'customerPayments', 'saleReturns'])
            ->where('is_credit', true)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->get();

        $rows = $sales->map(function ($sale) {
            $outstanding = $sale->outstandingBalance();
            if ($outstanding < 0.01) return null;

            $saleDate  = $sale->created_at instanceof \Carbon\Carbon ? $sale->created_at : Carbon::parse($sale->created_at);
            $ageInDays = (int) abs($saleDate->copy()->startOfDay()->diffInDays(now()->startOfDay()));
            return [
                'customer'     => $sale->customer?->name ?? '—',
                'customer_id'  => $sale->customer_id,
                'invoice'      => $sale->invoice_number,
                'invoice_date' => $sale->created_at->toDateString(),
                'total'        => (float) $sale->total_amount,
                'outstanding'  => $outstanding,
                'age_days'     => $ageInDays,
                'bucket'       => match (true) {
                    $ageInDays <= 30 => 'current',
                    $ageInDays <= 60 => '31_60',
                    $ageInDays <= 90 => '61_90',
                    default          => 'over_90',
                },
            ];
        })->filter()->sortByDesc('age_days')->values()->all();

        if ($request->ajax()) {
            return \Yajra\DataTables\Facades\DataTables::collection($rows)
                ->addColumn('customer_col', function ($r) {
                    return '<a href="' . route('customers.show', $r['customer_id']) . '" class="text-decoration-none fw-semibold">' . e($r['customer']) . '</a>';
                })
                ->addColumn('invoice_col', fn($r) => '<span class="badge bg-light text-dark">' . e($r['invoice']) . '</span>')
                ->addColumn('total_fmt', fn($r) => number_format($r['total'], 2))
                ->addColumn('outstanding_fmt', function($r) {
                    $cls = $r['age_days'] > 60 ? 'text-danger' : '';
                    return '<span class="fw-bold ' . $cls . '">' . number_format($r['outstanding'], 2) . '</span>';
                })
                ->addColumn('age_fmt', function($r) {
                    $cls = $r['age_days'] > 90 ? 'text-danger fw-bold' : '';
                    return '<span class="' . $cls . '">' . $r['age_days'] . '</span>';
                })
                ->addColumn('bucket_col', function($r) {
                    return match($r['bucket']) {
                        'current' => '<span class="badge bg-primary">جاري</span>',
                        '31_60'   => '<span class="badge bg-warning text-dark">31–60</span>',
                        '61_90'   => '<span class="badge" style="background:#fd7e14;color:white">61–90</span>',
                        default   => '<span class="badge bg-danger">+90 يوم</span>',
                    };
                })
                ->rawColumns(['customer_col', 'invoice_col', 'outstanding_fmt', 'age_fmt', 'bucket_col'])
                ->make(true);
        }

        $buckets = [
            'current' => collect($rows)->where('bucket', 'current')->sum('outstanding'),
            '31_60'   => collect($rows)->where('bucket', '31_60')->sum('outstanding'),
            '61_90'   => collect($rows)->where('bucket', '61_90')->sum('outstanding'),
            'over_90' => collect($rows)->where('bucket', 'over_90')->sum('outstanding'),
        ];
        $totalOutstanding = array_sum($buckets);

        return view('reports.ar_aging', compact(
            'buckets', 'totalOutstanding', 'cur',
            'branches', 'branchId', 'branchLocked'
        ));
    }

    // ── تقارير المخزون الذكية (مستوحاة من مكتبة تقارير الأصيل) ─────────────────

    /** تنبيهات المخزون: أصناف دون حد إعادة الطلب + أصناف فوق الحد الأقصى */
    public function stockAlerts(Request $request)
    {
        $cur      = Setting::get('currency_symbol', 'ج.م');
        $products = Product::with('category')
            ->where('product_type', '!=', Product::TYPE_SERVICE)
            ->get();

        $belowReorder = $products->filter(function ($p) {
            $level = $p->reorder_level ?? $p->min_quantity;
            return $level !== null && (float) $level > 0 && (float) $p->quantity <= (float) $level;
        })->sortBy('quantity')->values();

        $overstock = $products->filter(function ($p) {
            return $p->max_quantity !== null && (float) $p->max_quantity > 0
                && (float) $p->quantity > (float) $p->max_quantity;
        })->sortByDesc('quantity')->values();

        return view('reports.stock-alerts', compact('belowReorder', 'overstock', 'cur'));
    }

    /** الأصناف الراكدة: لها رصيد موجب لكن دون أي مبيعات خلال المدة المحددة */
    public function deadStock(Request $request)
    {
        $cur    = Setting::get('currency_symbol', 'ج.م');
        $days   = max(1, (int) $request->input('days', 90));
        $cutoff = now()->subDays($days);

        $soldIds = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.created_at', '>=', $cutoff)
            ->distinct()->pluck('sale_items.product_id')->all();

        $products = Product::with('category')
            ->where('product_type', '!=', Product::TYPE_SERVICE)
            ->where('quantity', '>', 0)
            ->when($soldIds, fn($q) => $q->whereNotIn('id', $soldIds))
            ->orderByDesc(DB::raw('quantity * cost_price'))
            ->get();

        $totalTied = $products->sum(fn($p) => (float) $p->quantity * (float) $p->cost_price);

        return view('reports.dead-stock', compact('products', 'days', 'totalTied', 'cur'));
    }

    /** سجل تغيّر أسعار التكلفة (مستوحى من "التغير في أسعار الشراء والبيع") */
    public function priceChanges(Request $request)
    {
        $cur      = Setting::get('currency_symbol', 'ج.م');
        $dateFrom = $request->input('date_from', now()->subDays(90)->toDateString());
        $dateTo   = $request->input('date_to',   now()->toDateString());

        $changes = \App\Models\CostPriceHistory::with('product:id,name,barcode', 'changedBy:id,name')
            ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->orderByDesc('created_at')
            ->get();

        return view('reports.price-changes', compact('changes', 'dateFrom', 'dateTo', 'cur'));
    }

    /** تقرير خصم المصدر (Withholding Tax) — المستقطع من المقبوضات عبر سندات القبض */
    public function withholding(Request $request)
    {
        $cur      = Setting::get('currency_symbol', 'ج.م');
        $dateFrom = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo   = $request->input('date_to',   now()->endOfMonth()->toDateString());

        $vouchers = \App\Models\ReceiptVoucher::with('account')
            ->where('source_discount_amount', '>', 0)
            ->whereBetween('voucher_date', [$dateFrom, $dateTo])
            ->orderByDesc('voucher_date')
            ->get();

        $total = $vouchers->sum('source_discount_amount');

        return view('reports.withholding', compact('vouchers', 'dateFrom', 'dateTo', 'total', 'cur'));
    }

    /**
     * تقييم المخزون: التكلفة مقابل صافي القيمة البيعية (Lower of Cost or NRV — IAS 2).
     * صافي القيمة البيعية = سعر البيع المقدّر − تكاليف بيع تقديرية (٪ من الإعدادات).
     * قيمة المخزون المعتمدة = أقل من (التكلفة، NRV) × الكمية؛ والفرق = هبوط قيمة واجب.
     */
    public function inventoryValuation(Request $request)
    {
        $cur          = Setting::get('currency_symbol', 'ج.م');
        $sellCostPct  = (float) Setting::get('nrv_selling_cost_percent', 0);
        $onlyWritedown = $request->boolean('only_writedown');

        $all = Product::with('category')
            ->where('product_type', '!=', Product::TYPE_SERVICE)
            ->where('quantity', '>', 0)
            ->get()
            ->map(function ($p) use ($sellCostPct) {
                $qty  = (float) $p->quantity;
                $cost = (float) $p->cost_price;
                $nrvU = round((float) $p->selling_price * (1 - $sellCostPct / 100), 2);
                $lcmU = min($cost, $nrvU);
                return (object) [
                    'product'    => $p,
                    'qty'        => $qty,
                    'cost_unit'  => $cost,
                    'nrv_unit'   => $nrvU,
                    'lcm_unit'   => $lcmU,
                    'cost_value' => round($qty * $cost, 2),
                    'nrv_value'  => round($qty * $nrvU, 2),
                    'lcm_value'  => round($qty * $lcmU, 2),
                    'writedown'  => round($qty * max(0, $cost - $nrvU), 2),
                ];
            });

        $totals = [
            'cost'      => round($all->sum('cost_value'), 2),
            'nrv'       => round($all->sum('nrv_value'), 2),
            'lcm'       => round($all->sum('lcm_value'), 2),
            'writedown' => round($all->sum('writedown'), 2),
        ];

        $rows = $onlyWritedown
            ? $all->filter(fn($r) => $r->writedown > 0.005)->values()
            : $all->values();

        return view('reports.inventory-valuation', compact('rows', 'totals', 'sellCostPct', 'onlyWritedown', 'cur'));
    }

    /**
     * تقييم المخزون النهائي بطريقة FIFO (الوارد أولاً صادر أولاً) — IAS 2 §25.
     * المخزون المتبقي = أحدث طبقات الشراء؛ يُقيَّم بأسعار أحدث الفواتير حتى استيفاء الكمية،
     * ويُقارن بقيمة AVCO الحالية.
     */
    public function fifoValuation(Request $request)
    {
        $cur = Setting::get('currency_symbol', 'ج.م');

        $products = Product::with('category')
            ->where('product_type', '!=', Product::TYPE_SERVICE)
            ->where('quantity', '>', 0)->get();

        $layers = DB::table('purchase_items')
            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->whereIn('purchase_items.product_id', $products->pluck('id'))
            ->orderByDesc('purchases.created_at')
            ->select('purchase_items.product_id', 'purchase_items.quantity', 'purchase_items.unit_price')
            ->get()
            ->groupBy('product_id');

        $rows = $products->map(function ($p) use ($layers) {
            $need = (float) $p->quantity;
            $val = 0.0; $filled = 0.0;
            foreach ($layers->get($p->id, collect()) as $L) {
                if ($filled >= $need - 0.0001) break;
                $take = min((float) $L->quantity, $need - $filled);
                $val += $take * (float) $L->unit_price;
                $filled += $take;
            }
            // كمية بلا تاريخ شراء كافٍ → تُقيَّم بالتكلفة الحالية
            if ($filled < $need - 0.0001) {
                $val += ($need - $filled) * (float) $p->cost_price;
            }
            return (object) [
                'product'    => $p,
                'qty'        => $need,
                'fifo_value' => round($val, 2),
                'avco_value' => round($need * (float) $p->cost_price, 2),
            ];
        })->values();

        $totals = [
            'fifo' => round($rows->sum('fifo_value'), 2),
            'avco' => round($rows->sum('avco_value'), 2),
        ];

        return view('reports.fifo-valuation', compact('rows', 'totals', 'cur'));
    }

    /** حاسبة تكلفة الاستيراد (Landed Cost) — أداة قرار قبل الاستيراد والتسعير (حساب في المتصفح) */
    public function landedCost(Request $request)
    {
        $cur = Setting::get('currency_symbol', 'ج.م');
        return view('reports.landed-cost', compact('cur'));
    }

    /** رسوم تحليلية: المبيعات والمشتريات الشهرية لآخر ١٢ شهراً */
    public function analytics(Request $request)
    {
        $cur    = Setting::get('currency_symbol', 'ج.م');
        $months = collect(range(0, 11))->map(fn($i) => now()->startOfMonth()->subMonths($i))->reverse()->values();

        $labels = $months->map(fn($m) => $m->format('Y-m'))->all();
        $sales = $months->map(fn($m) => (float) DB::table('sales')
            ->whereBetween('created_at', [$m->copy()->startOfMonth(), $m->copy()->endOfMonth()])
            ->sum('total_amount'))->all();
        $purchases = $months->map(fn($m) => (float) DB::table('purchases')
            ->whereBetween('created_at', [$m->copy()->startOfMonth(), $m->copy()->endOfMonth()])
            ->sum('total_amount'))->all();

        return view('reports.analytics', compact('labels', 'sales', 'purchases', 'cur'));
    }
}
