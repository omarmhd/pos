<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $todaySales = Sale::whereDate('created_at', today())->sum('total_amount');
        $monthSales = Sale::whereMonth('created_at', now()->month)->sum('total_amount');
        $totalProducts = Product::count();
        $lowStockProducts = Product::whereRaw('quantity <= min_quantity')->count();

        $recentSales = Sale::with('user', 'items.product')
            ->latest()
            ->take(5)
            ->get();

        $topProducts = Product::withCount('saleItems')
            ->orderBy('sale_items_count', 'desc')
            ->take(5)
            ->get();

        $expiringProducts = Product::whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now(), now()->addDays(30)])
            ->get();

        return view('dashboard', compact(
            'todaySales',
            'monthSales',
            'totalProducts',
            'lowStockProducts',
            'recentSales',
            'topProducts',
            'expiringProducts'
        ));
    }
}
