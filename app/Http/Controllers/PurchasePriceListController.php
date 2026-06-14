<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PurchasePriceList;
use App\Models\PurchaseProductPrice;
use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * قوائم أسعار الشراء (مستوحاة من "5 فئات أسعار شراء" في الأصيل):
 * كل قائمة تضم أسعار شراء للأصناف، ويُربط كل مورد بقائمة،
 * فتُقترح التكلفة تلقائياً عند إدخال فاتورة شراء لذلك المورد.
 */
class PurchasePriceListController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:purchase_price_lists.view')->only(['index', 'products']);
        $this->middleware('can:purchase_price_lists.manage')->only(['create', 'store', 'edit', 'update', 'destroy']);
    }

    public function index()
    {
        $priceLists = PurchasePriceList::withCount('productPrices', 'suppliers')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view('settings.purchase-price-lists.index', compact('priceLists'));
    }

    public function create()
    {
        return view('settings.purchase-price-lists.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $list = PurchasePriceList::create($data);

        return redirect()->route('purchase-price-lists.index')
            ->with('success', 'تم إنشاء قائمة أسعار الشراء — ' . $list->name);
    }

    public function edit(PurchasePriceList $purchasePriceList)
    {
        return view('settings.purchase-price-lists.edit', compact('purchasePriceList'));
    }

    public function update(Request $request, PurchasePriceList $purchasePriceList)
    {
        $purchasePriceList->update($this->validated($request, $purchasePriceList));

        return redirect()->route('purchase-price-lists.index')
            ->with('success', 'تم تحديث قائمة أسعار الشراء');
    }

    public function destroy(PurchasePriceList $purchasePriceList)
    {
        if ($purchasePriceList->suppliers()->exists()) {
            return back()->with('error', 'لا يمكن حذف قائمة مرتبطة بموردين — أعد تعيينهم أولاً');
        }
        $purchasePriceList->delete();

        return redirect()->route('purchase-price-lists.index')->with('success', 'تم حذف القائمة');
    }

    /**
     * عرض/حفظ أسعار الشراء للأصناف داخل قائمة معينة.
     * GET:  جميع الأصناف مع أسعارها الحالية في القائمة
     * POST: حفظ جماعي — prices[product_id] = cost
     */
    public function products(Request $request, PurchasePriceList $purchasePriceList)
    {
        if ($request->isMethod('post')) {
            $this->authorize('purchase_price_lists.manage');

            foreach ((array) $request->input('prices', []) as $productId => $cost) {
                if ($cost === null || $cost === '') {
                    PurchaseProductPrice::where('purchase_price_list_id', $purchasePriceList->id)
                        ->where('product_id', (int) $productId)
                        ->delete();
                    continue;
                }
                PurchaseProductPrice::updateOrCreate(
                    [
                        'purchase_price_list_id' => $purchasePriceList->id,
                        'product_id'             => (int) $productId,
                    ],
                    ['cost_price' => round((float) $cost, 2)]
                );
            }

            return back()->with('success', 'تم حفظ أسعار الشراء');
        }

        $currency = Setting::get('currency_symbol', 'ج.م');

        $products = Product::where('product_type', Product::TYPE_GOODS)
            ->orderBy('name')
            ->get(['id', 'name', 'barcode', 'cost_price'])
            ->each(function ($p) use ($purchasePriceList) {
                $p->list_cost = PurchaseProductPrice::where('purchase_price_list_id', $purchasePriceList->id)
                    ->where('product_id', $p->id)
                    ->value('cost_price');
            });

        return view('settings.purchase-price-lists.products', compact('purchasePriceList', 'products', 'currency'));
    }

    private function validated(Request $request, ?PurchasePriceList $list = null): array
    {
        $data = $request->validate([
            'code'        => 'required|string|max:20|unique:purchase_price_lists,code' . ($list ? ',' . $list->id : ''),
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string',
            'is_default'  => 'nullable|boolean',
            'is_active'   => 'nullable|boolean',
        ]);
        $data['is_default'] = $request->boolean('is_default');
        $data['is_active']  = $request->boolean('is_active', true);

        return $data;
    }
}
