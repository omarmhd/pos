<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $currency = Setting::get('currency_symbol', 'ج.م');

        // ── مبيعات ──────────────────────────────────────────────
        $todaySales   = Sale::whereDate('created_at', today())->sum('total_amount');
        $yesterdaySales = Sale::whereDate('created_at', today()->subDay())->sum('total_amount');
        $monthSales   = Sale::whereMonth('created_at', now()->month)
                            ->whereYear('created_at',  now()->year)->sum('total_amount');
        $todayInvoices = Sale::whereDate('created_at', today())->count();

        // ── مخزون ───────────────────────────────────────────────
        $totalProducts   = Product::count();
        $lowStockProducts = Product::whereRaw('quantity <= min_quantity')->count();
        $expiringProducts = Product::whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now(), now()->addDays(30)])
            ->get();

        // ── خزينة: سندات معلقة ──────────────────────────────────
        $pendingVouchers = 0;
        try {
            $pendingVouchers = \App\Models\Voucher::whereNull('posted_at')->count();
        } catch (\Throwable) {}

        // ── وردية المستخدم الحالي ────────────────────────────────
        $openShift = null;
        try {
            $openShift = \App\Models\CashShift::activeForUser(auth()->id());
        } catch (\Throwable) {}

        // ── إجازات معلقة ────────────────────────────────────────
        $pendingLeaves = 0;
        try {
            $pendingLeaves = \App\Models\EmployeeLeave::where('status', 'pending')->count();
        } catch (\Throwable) {}

        // ── فواتير شراء (الشهر) ──────────────────────────────────
        $monthPurchases = 0;
        try {
            $monthPurchases = \App\Models\Purchase::whereMonth('invoice_date', now()->month)
                ->whereYear('invoice_date', now()->year)->sum('total_amount');
        } catch (\Throwable) {}

        // ── آخر المبيعات ─────────────────────────────────────────
        $recentSales = Sale::with('user')
            ->latest()->take(7)->get();

        // ── أكثر المنتجات مبيعاً ────────────────────────────────
        $topProducts = Product::withCount('saleItems')
            ->orderBy('sale_items_count', 'desc')
            ->take(5)->get();

        return view('dashboard', compact(
            'currency',
            'todaySales', 'yesterdaySales', 'monthSales', 'todayInvoices',
            'totalProducts', 'lowStockProducts', 'expiringProducts',
            'pendingVouchers', 'openShift', 'pendingLeaves', 'monthPurchases',
            'recentSales', 'topProducts'
        ));
    }
}
