<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:currencies.view')->only(['index']);
        $this->middleware('can:currencies.manage')->only(['create', 'store', 'edit', 'update', 'destroy']);
    }

    public function index()
    {
        $currencies = Currency::withCount('products')->orderByDesc('is_base')->orderBy('code')->get();
        return view('settings.currencies.index', compact('currencies'));
    }

    public function create()
    {
        return view('settings.currencies.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        Currency::create($data);

        return redirect()->route('currencies.index')->with('success', 'تم إضافة العملة');
    }

    public function edit(Currency $currency)
    {
        return view('settings.currencies.edit', compact('currency'));
    }

    public function update(Request $request, Currency $currency)
    {
        $data = $this->validated($request, $currency);
        $currency->update($data);

        return redirect()->route('currencies.index')->with('success', 'تم تحديث العملة وسعر الصرف');
    }

    public function destroy(Currency $currency)
    {
        if ($currency->is_base) {
            return back()->with('error', 'لا يمكن حذف العملة الأساسية');
        }
        if ($currency->products()->exists()) {
            return back()->with('error', 'لا يمكن حذف عملة مرتبطة بأصناف — أزل ربطها أولاً');
        }
        $currency->delete();

        return redirect()->route('currencies.index')->with('success', 'تم حذف العملة');
    }

    private function validated(Request $request, ?Currency $currency = null): array
    {
        $data = $request->validate([
            'code'          => 'required|string|max:10|unique:currencies,code' . ($currency ? ',' . $currency->id : ''),
            'name'          => 'required|string|max:50',
            'symbol'        => 'required|string|max:10',
            'exchange_rate' => 'required|numeric|min:0.000001',
            'is_base'       => 'nullable|boolean',
            'is_active'     => 'nullable|boolean',
        ]);
        $data['is_base']   = $request->boolean('is_base');
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
