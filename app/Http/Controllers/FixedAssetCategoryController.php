<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\FixedAssetCategory;
use Illuminate\Http\Request;

class FixedAssetCategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:fixed_assets.create');
    }

    public function index()
    {
        $categories = FixedAssetCategory::with('assetAccount', 'accumulatedDepAccount', 'depreciationExpAccount')
            ->withCount('assets')
            ->orderBy('name')
            ->get();

        return view('fixed-assets.categories.index', compact('categories'));
    }

    public function create()
    {
        $expenseAccounts = Account::where('type', 'expense')->where('is_header', false)->where('is_active', true)->orderBy('code')->get(['id','code','name']);
        $assetAccounts   = Account::where('type', 'asset')  ->where('is_header', false)->where('is_active', true)->orderBy('code')->get(['id','code','name']);
        return view('fixed-assets.categories.create', compact('expenseAccounts', 'assetAccounts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'code'                            => 'required|string|max:30|unique:fixed_asset_categories,code',
            'name'                            => 'required|string|max:100',
            'asset_account_id'                => 'required|exists:accounts,id',
            'accumulated_dep_account_id'      => 'required|exists:accounts,id',
            'depreciation_expense_account_id' => 'required|exists:accounts,id',
            'depreciation_method'             => 'required|in:straight_line,declining_balance',
            'useful_life_months'              => 'required|integer|min:1',
        ]);

        FixedAssetCategory::create($request->only([
            'code','name',
            'asset_account_id','accumulated_dep_account_id','depreciation_expense_account_id',
            'depreciation_method','useful_life_months',
        ]) + ['is_active' => true]);

        return redirect()->route('fixed-asset-categories.index')
            ->with('success', 'تم إنشاء الفئة بنجاح');
    }

    public function edit(FixedAssetCategory $fixedAssetCategory)
    {
        $expenseAccounts = Account::where('type', 'expense')->where('is_header', false)->where('is_active', true)->orderBy('code')->get(['id','code','name']);
        $assetAccounts   = Account::where('type', 'asset')  ->where('is_header', false)->where('is_active', true)->orderBy('code')->get(['id','code','name']);
        return view('fixed-assets.categories.edit', compact('fixedAssetCategory', 'expenseAccounts', 'assetAccounts'));
    }

    public function update(Request $request, FixedAssetCategory $fixedAssetCategory)
    {
        $request->validate([
            'code'                            => 'required|string|max:30|unique:fixed_asset_categories,code,'.$fixedAssetCategory->id,
            'name'                            => 'required|string|max:100',
            'asset_account_id'                => 'required|exists:accounts,id',
            'accumulated_dep_account_id'      => 'required|exists:accounts,id',
            'depreciation_expense_account_id' => 'required|exists:accounts,id',
            'depreciation_method'             => 'required|in:straight_line,declining_balance',
            'useful_life_months'              => 'required|integer|min:1',
        ]);

        $fixedAssetCategory->update($request->only([
            'code','name',
            'asset_account_id','accumulated_dep_account_id','depreciation_expense_account_id',
            'depreciation_method','useful_life_months',
        ]));

        return redirect()->route('fixed-asset-categories.index')->with('success', 'تم التحديث');
    }
}
