<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Services\WarehouseService;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:branches.view')->only(['index', 'stock']);
        $this->middleware('can:branches.manage')->only(['create', 'store', 'edit', 'update', 'destroy']);
    }

    public function index()
    {
        $warehouses = Warehouse::with('branch')
            ->withCount('stockLevels')
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return view('settings.warehouses.index', compact('warehouses'));
    }

    public function create()
    {
        $branches = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        return view('settings.warehouses.create', compact('branches'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'       => 'required|string|max:20|unique:warehouses,code',
            'name'       => 'required|string|max:100',
            'type'       => 'required|in:main,floor,returns,transit',
            'branch_id'  => 'nullable|exists:branches,id',
            'is_default' => 'boolean',
            'is_active'  => 'boolean',
            'notes'      => 'nullable|string',
        ]);

        $data['is_default'] = $request->boolean('is_default');
        $data['is_active']  = $request->boolean('is_active', true);

        $warehouse = Warehouse::create($data);

        if ($warehouse->is_default) {
            Setting::set('default_warehouse_id', $warehouse->id);
        }

        return redirect()->route('warehouses.index')
            ->with('success', 'تم إنشاء المخزن بنجاح — ' . $warehouse->name);
    }

    public function edit(Warehouse $warehouse)
    {
        $branches = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        return view('settings.warehouses.edit', compact('warehouse', 'branches'));
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $data = $request->validate([
            'code'       => 'required|string|max:20|unique:warehouses,code,' . $warehouse->id,
            'name'       => 'required|string|max:100',
            'type'       => 'required|in:main,floor,returns,transit',
            'branch_id'  => 'nullable|exists:branches,id',
            'is_default' => 'boolean',
            'is_active'  => 'boolean',
            'notes'      => 'nullable|string',
        ]);

        $data['is_default'] = $request->boolean('is_default');
        $data['is_active']  = $request->boolean('is_active', true);

        $warehouse->update($data);

        if ($warehouse->is_default) {
            Setting::set('default_warehouse_id', $warehouse->id);
        }

        return redirect()->route('warehouses.index')
            ->with('success', 'تم تحديث المخزن بنجاح');
    }

    public function destroy(Warehouse $warehouse)
    {
        if ($warehouse->is_default) {
            return back()->with('error', 'لا يمكن حذف المخزن الافتراضي');
        }
        if ($warehouse->stockLevels()->where('quantity', '>', 0)->exists()) {
            return back()->with('error', 'لا يمكن حذف مخزن يحتوي على مخزون — حوّل البضاعة أولاً');
        }

        $warehouse->delete();
        return redirect()->route('warehouses.index')->with('success', 'تم حذف المخزن');
    }

    /** Stock levels report for a specific warehouse */
    public function stock(Warehouse $warehouse, Request $request)
    {
        $currency   = \App\Models\Setting::get('currency_symbol', 'ج.م');
        $stockItems = WarehouseService::stockForWarehouse($warehouse->id);

        return view('settings.warehouses.stock', compact('warehouse', 'stockItems', 'currency'));
    }
}
