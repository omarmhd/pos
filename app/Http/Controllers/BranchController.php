<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Setting;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:branches.view')->only(['index']);
        $this->middleware('can:branches.manage')->only(['create', 'store', 'edit', 'update', 'destroy']);
    }

    public function index()
    {
        $branches = Branch::withCount('warehouses')->orderBy('is_default', 'desc')->orderBy('name')->get();
        return view('settings.branches.index', compact('branches'));
    }

    public function create()
    {
        return view('settings.branches.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'       => 'required|string|max:20|unique:branches,code',
            'name'       => 'required|string|max:100',
            'type'       => 'required|in:' . implode(',', array_keys(Branch::$types)),
            'address'    => 'nullable|string',
            'phone'      => 'nullable|string|max:30',
            'is_default' => 'boolean',
            'is_active'  => 'boolean',
            'notes'      => 'nullable|string',
        ]);

        $data['is_default'] = $request->boolean('is_default');
        $data['is_active']  = $request->boolean('is_active', true);

        $branch = Branch::create($data);

        if ($branch->is_default) {
            Setting::set('default_branch_id', $branch->id);
        }

        return redirect()->route('branches.index')
            ->with('success', 'تم إنشاء الفرع بنجاح — ' . $branch->name);
    }

    public function edit(Branch $branch)
    {
        return view('settings.branches.edit', compact('branch'));
    }

    public function update(Request $request, Branch $branch)
    {
        $data = $request->validate([
            'code'       => 'required|string|max:20|unique:branches,code,' . $branch->id,
            'name'       => 'required|string|max:100',
            'type'       => 'required|in:' . implode(',', array_keys(Branch::$types)),
            'address'    => 'nullable|string',
            'phone'      => 'nullable|string|max:30',
            'is_default' => 'boolean',
            'is_active'  => 'boolean',
            'notes'      => 'nullable|string',
        ]);

        $data['is_default'] = $request->boolean('is_default');
        $data['is_active']  = $request->boolean('is_active', true);

        $branch->update($data);

        if ($branch->is_default) {
            Setting::set('default_branch_id', $branch->id);
        }

        return redirect()->route('branches.index')
            ->with('success', 'تم تحديث الفرع بنجاح');
    }

    public function destroy(Branch $branch)
    {
        if ($branch->is_default) {
            return back()->with('error', 'لا يمكن حذف الفرع الافتراضي');
        }
        if ($branch->warehouses()->exists()) {
            return back()->with('error', 'لا يمكن حذف فرع يحتوي على مخازن — احذف المخازن أولاً');
        }

        $branch->delete();
        return redirect()->route('branches.index')->with('success', 'تم حذف الفرع');
    }
}
