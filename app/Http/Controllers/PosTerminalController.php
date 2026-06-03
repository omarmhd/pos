<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\PosTerminal;
use App\Models\PriceList;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class PosTerminalController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:pos_terminals.view')->only(['index']);
        $this->middleware('can:pos_terminals.manage')->only(['create', 'store', 'edit', 'update', 'destroy']);
    }

    public function index()
    {
        $terminals = PosTerminal::with('branch', 'warehouse', 'priceList')
            ->withCount('users')
            ->orderBy('branch_id')
            ->orderBy('code')
            ->get();

        return view('settings.pos-terminals.index', compact('terminals'));
    }

    public function create()
    {
        $branches   = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        $warehouses = Warehouse::where('is_active', true)->with('branch:id,name')->orderBy('name')->get();
        $priceLists = PriceList::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        return view('settings.pos-terminals.create', compact('branches', 'warehouses', 'priceLists'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'code'          => 'required|string|max:30|unique:pos_terminals,code',
            'name'          => 'required|string|max:100',
            'branch_id'     => 'required|exists:branches,id',
            'warehouse_id'  => 'required|exists:warehouses,id',
            'price_list_id' => 'nullable|exists:price_lists,id',
            'is_active'     => 'boolean',
            'notes'         => 'nullable|string',
        ]);

        PosTerminal::create([
            'code'          => $request->code,
            'name'          => $request->name,
            'branch_id'     => $request->branch_id,
            'warehouse_id'  => $request->warehouse_id,
            'price_list_id' => $request->price_list_id ?: null,
            'is_active'     => $request->boolean('is_active', true),
            'notes'         => $request->notes,
        ]);

        return redirect()->route('pos-terminals.index')
            ->with('success', 'تم إنشاء نقطة البيع — ' . $request->code);
    }

    public function edit(PosTerminal $posTerminal)
    {
        $branches   = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        $warehouses = Warehouse::where('is_active', true)->with('branch:id,name')->orderBy('name')->get();
        $priceLists = PriceList::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        return view('settings.pos-terminals.edit', compact('posTerminal', 'branches', 'warehouses', 'priceLists'));
    }

    public function update(Request $request, PosTerminal $posTerminal)
    {
        $request->validate([
            'code'          => 'required|string|max:30|unique:pos_terminals,code,' . $posTerminal->id,
            'name'          => 'required|string|max:100',
            'branch_id'     => 'required|exists:branches,id',
            'warehouse_id'  => 'required|exists:warehouses,id',
            'price_list_id' => 'nullable|exists:price_lists,id',
            'is_active'     => 'boolean',
            'notes'         => 'nullable|string',
        ]);

        $posTerminal->update([
            'code'          => $request->code,
            'name'          => $request->name,
            'branch_id'     => $request->branch_id,
            'warehouse_id'  => $request->warehouse_id,
            'price_list_id' => $request->price_list_id ?: null,
            'is_active'     => $request->boolean('is_active', true),
            'notes'         => $request->notes,
        ]);

        return redirect()->route('pos-terminals.index')->with('success', 'تم التحديث');
    }

    public function destroy(PosTerminal $posTerminal)
    {
        if ($posTerminal->users()->exists()) {
            return back()->with('error', 'لا يمكن حذف نقطة بيع مرتبطة بمستخدمين');
        }
        $posTerminal->delete();
        return redirect()->route('pos-terminals.index')->with('success', 'تم الحذف');
    }
}
