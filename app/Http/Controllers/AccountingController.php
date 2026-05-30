<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Product;
use App\Models\JournalEntry;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AccountingController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:accounts.view');
    }

    public function index(Request $request)
    {
        $dateFrom = Carbon::parse($request->input('date_from', now()->startOfMonth()))->startOfDay();
        $dateTo = Carbon::parse($request->input('date_to', now()->endOfMonth()))->endOfDay();

        $salesAgg = Sale::whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total_amount), 0) as revenue')
            ->first();
        $salesRevenue = (float) $salesAgg->revenue;
        $salesCount   = (int)   $salesAgg->count;

        $purchasesAgg = Purchase::whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw('COALESCE(SUM(total_amount), 0) as value, COALESCE(SUM(paid_amount), 0) as paid')
            ->first();
        $purchaseValue       = (float) $purchasesAgg->value;
        $purchasePaid        = (float) $purchasesAgg->paid;
        $purchaseOutstanding = max(0, $purchaseValue - $purchasePaid);

        $profitData = SaleItem::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->whereBetween('sales.created_at', [$dateFrom, $dateTo])
            ->where('sales.is_posted', true)
            ->selectRaw('COALESCE(SUM(sale_items.total_price), 0) as revenue,
                         COALESCE(SUM(sale_items.quantity * sale_items.cost_price), 0) as cost')
            ->first();

        $grossRevenue = (float) ($profitData->revenue ?? 0);
        $costOfGoods = (float) ($profitData->cost ?? 0);
        $grossProfit = $grossRevenue - $costOfGoods;
        $grossMargin = $grossRevenue > 0 ? ($grossProfit / $grossRevenue) * 100 : 0;

        $inventoryValue = (float) (Product::query()
            ->selectRaw('COALESCE(SUM(quantity * cost_price), 0) as inventory_value')
            ->value('inventory_value') ?? 0);

        $lowStockCount = Product::whereRaw('quantity <= min_quantity')->count();
        $expiringSoonCount = Product::whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now(), now()->addDays(30)])
            ->count();

        $paymentTotals = Sale::whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw('payment_method, COALESCE(SUM(total_amount), 0) as total')
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method');
        $paymentBreakdown = [
            'cash'          => (float) ($paymentTotals['cash']          ?? 0),
            'card'          => (float) ($paymentTotals['card']          ?? 0),
            'mobile_wallet' => (float) ($paymentTotals['mobile_wallet'] ?? 0),
        ];

        $cashShare = $salesRevenue > 0 ? min(100, ($paymentBreakdown['cash'] / $salesRevenue) * 100) : 0;
        $cardShare = $salesRevenue > 0 ? min(100, ($paymentBreakdown['card'] / $salesRevenue) * 100) : 0;
        $walletShare = $salesRevenue > 0 ? min(100, ($paymentBreakdown['mobile_wallet'] / $salesRevenue) * 100) : 0;

        $dailySales = Sale::whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('COUNT(*) as invoices_count')
            ->selectRaw('SUM(total_amount) as sales_total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $recentSales = Sale::with('user')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->latest()
            ->take(5)
            ->get();

        $recentPurchases = Purchase::with('supplier', 'user')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->latest()
            ->take(5)
            ->get();

        $supplierBalances = Purchase::with('supplier')
            ->selectRaw('supplier_id, SUM(total_amount - paid_amount) as balance')
            ->whereIn('payment_status', ['partial', 'unpaid'])
            ->groupBy('supplier_id')
            ->orderByDesc('balance')
            ->take(5)
            ->get();

        $accounts = Account::withCount('journalEntryLines')
            ->orderBy('code')
            ->get();

        $journalEntries = JournalEntry::with(['user', 'lines.account'])
            ->latest('entry_date')
            ->latest('id')
            ->take(8)
            ->get();

        $accountSummary = Account::query()
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return view('accounting.index', compact(
            'dateFrom',
            'dateTo',
            'salesRevenue',
            'salesCount',
            'purchaseValue',
            'purchasePaid',
            'purchaseOutstanding',
            'grossRevenue',
            'costOfGoods',
            'grossProfit',
            'grossMargin',
            'inventoryValue',
            'lowStockCount',
            'expiringSoonCount',
            'paymentBreakdown',
            'cashShare',
            'cardShare',
            'walletShare',
            'dailySales',
            'recentSales',
            'recentPurchases',
            'supplierBalances',
            'accounts',
            'journalEntries',
            'accountSummary'
        ));
    }
}