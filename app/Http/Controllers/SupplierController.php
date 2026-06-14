<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:suppliers.view')->only(['index', 'show']);
        $this->middleware('can:suppliers.create')->only(['create', 'store']);
        $this->middleware('can:suppliers.edit')->only(['edit', 'update']);
        $this->middleware('can:suppliers.delete')->only(['destroy']);
    }

    public function index()
    {
        $suppliers = Supplier::withCount('purchases')->orderBy('name')->get();
        return view('suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        $purchasePriceLists = \App\Models\PurchasePriceList::where('is_active', true)->orderBy('name')->get();
        return view('suppliers.create', compact('purchasePriceLists'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'nullable|string',
            'company' => 'nullable|string|max:255',
            'tax_number' => 'nullable|string|max:50',
            'purchase_price_list_id' => 'nullable|exists:purchase_price_lists,id',
        ]);

        Supplier::create($validated);

        return redirect()->route('suppliers.index')
            ->with('success', 'تم إضافة المورد بنجاح');
    }

    public function show(Supplier $supplier)
    {
        $supplier->load(['purchases' => function($query) {
            $query->latest()->take(10);
        }]);

        return view('suppliers.show', compact('supplier'));
    }

    public function edit(Supplier $supplier)
    {
        $purchasePriceLists = \App\Models\PurchasePriceList::where('is_active', true)->orderBy('name')->get();
        return view('suppliers.edit', compact('supplier', 'purchasePriceLists'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'nullable|string',
            'company' => 'nullable|string|max:255',
            'tax_number' => 'nullable|string|max:50',
            'purchase_price_list_id' => 'nullable|exists:purchase_price_lists,id',
        ]);

        $supplier->update($validated);

        return redirect()->route('suppliers.index')
            ->with('success', 'تم تحديث المورد بنجاح');
    }

    public function destroy(Supplier $supplier)
    {
        if ($supplier->purchases()->count() > 0) {
            return back()->with('error', 'لا يمكن حذف المورد لأنه يحتوي على عمليات شراء');
        }

        $supplier->delete();

        return redirect()->route('suppliers.index')
            ->with('success', 'تم حذف المورد بنجاح');
    }
}
