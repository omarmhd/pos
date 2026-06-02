<?php

namespace App\Http\Controllers;

use App\Models\PriceList;
use App\Models\Product;
use App\Models\Setting;
use App\Services\PricingService;
use Illuminate\Http\Request;

class PriceListController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:price_lists.view')->only(['index', 'products']);
        $this->middleware('can:price_lists.manage')->only(['create', 'store', 'edit', 'update', 'destroy', 'saveProducts']);
    }

    public function index()
    {
        $priceLists = PriceList::withCount('productPrices', 'customers', 'branches')
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return view('settings.price-lists.index', compact('priceLists'));
    }

    public function create()
    {
        return view('settings.price-lists.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'        => 'required|string|max:20|unique:price_lists,code',
            'name'        => 'required|string|max:100',
            'type'        => 'required|in:' . implode(',', array_keys(PriceList::$types)),
            'description' => 'nullable|string',
            'is_default'  => 'boolean',
            'is_active'   => 'boolean',
        ]);

        $data['is_default'] = $request->boolean('is_default');
        $data['is_active']  = $request->boolean('is_active', true);

        $list = PriceList::create($data);

        if ($list->is_default) {
            Setting::set('default_price_list_id', $list->id);
        }

        return redirect()->route('price-lists.index')
            ->with('success', 'تم إنشاء قائمة الأسعار — ' . $list->name);
    }

    public function edit(PriceList $priceList)
    {
        return view('settings.price-lists.edit', compact('priceList'));
    }

    public function update(Request $request, PriceList $priceList)
    {
        $data = $request->validate([
            'code'        => 'required|string|max:20|unique:price_lists,code,' . $priceList->id,
            'name'        => 'required|string|max:100',
            'type'        => 'required|in:' . implode(',', array_keys(PriceList::$types)),
            'description' => 'nullable|string',
            'is_default'  => 'boolean',
            'is_active'   => 'boolean',
        ]);

        $data['is_default'] = $request->boolean('is_default');
        $data['is_active']  = $request->boolean('is_active', true);

        $priceList->update($data);

        if ($priceList->is_default) {
            Setting::set('default_price_list_id', $priceList->id);
        }

        return redirect()->route('price-lists.index')
            ->with('success', 'تم تحديث قائمة الأسعار');
    }

    public function destroy(PriceList $priceList)
    {
        if ($priceList->is_default) {
            return back()->with('error', 'لا يمكن حذف قائمة الأسعار الافتراضية');
        }
        if ($priceList->customers()->exists() || $priceList->branches()->exists()) {
            return back()->with('error', 'لا يمكن حذف قائمة لها عملاء أو فروع مرتبطون — أعد تعيينهم أولاً');
        }

        $priceList->delete();
        return redirect()->route('price-lists.index')->with('success', 'تم حذف قائمة الأسعار');
    }

    /**
     * Show + save per-product prices for a specific price list.
     * GET:  display all products with current prices in this list
     * POST: bulk-save price overrides
     */
    public function products(Request $request, PriceList $priceList)
    {
        if ($request->isMethod('post')) {
            $this->authorize('price_lists.manage');

            PricingService::savePricesForProduct(
                0, // placeholder — handled per-product below
                []
            );

            // Expect: prices[product_id][price] + prices[product_id][min_qty]
            $prices = $request->input('prices', []);
            foreach ($prices as $productId => $data) {
                PricingService::savePricesForProduct((int) $productId, [
                    $priceList->id => $data,
                ]);
            }

            return back()->with('success', 'تم حفظ الأسعار بنجاح');
        }

        $currency = Setting::get('currency_symbol', 'ج.م');

        // Load all products with their current prices in this list
        $products = Product::orderBy('name')
            ->with(['prices' => fn($q) => $q->where('price_list_id', $priceList->id)])
            ->get();

        return view('settings.price-lists.products', compact('priceList', 'products', 'currency'));
    }
}
