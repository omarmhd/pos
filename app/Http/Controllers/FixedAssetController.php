<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\Setting;
use App\Services\DepreciationService;
use App\Services\LedgerPostingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class FixedAssetController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:fixed_assets.view')->only(['index', 'show', 'schedule']);
        $this->middleware('can:fixed_assets.create')->only(['create', 'store']);
        $this->middleware('can:fixed_assets.depreciate')->only(['depreciateForm', 'depreciate', 'depreciateAll']);
        $this->middleware('can:fixed_assets.dispose')->only(['disposeForm', 'dispose']);
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $branchId = $this->effectiveBranchId($request);

            $query = FixedAsset::with('category', 'branch')
                ->select('fixed_assets.*')
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId));

            return DataTables::eloquent($query)
                ->addColumn('category_name',  fn($a) => e($a->category?->name ?? '—'))
                ->addColumn('branch_name',    fn($a) => e($a->branch?->name ?? '—'))
                ->addColumn('cost_fmt',       fn($a) => number_format($a->purchase_cost, 2))
                ->addColumn('accum_fmt',      fn($a) => number_format($a->accumulated_depreciation, 2))
                ->addColumn('nbv_fmt',        fn($a) => number_format($a->net_book_value, 2))
                ->addColumn('status_badge',   fn($a) => $this->statusBadge($a->status))
                ->addColumn('action',         fn($a) => $this->actionButtons($a))
                ->rawColumns(['status_badge', 'action'])
                ->make(true);
        }

        $branches     = \App\Models\Branch::where('is_active', true)->orderBy('name')->get(['id','name','code']);
        $branchId     = $this->effectiveBranchId($request);
        $branchLocked = $this->isBranchLocked();
        $currency     = Setting::get('currency_symbol', 'ج.م');

        return view('fixed-assets.index', compact('branches', 'branchId', 'branchLocked', 'currency'));
    }

    public function create()
    {
        $categories = FixedAssetCategory::where('is_active', true)->orderBy('name')->get();
        $currency   = Setting::get('currency_symbol', 'ج.م');
        $branches   = \App\Models\Branch::where('is_active', true)->orderBy('name')->get(['id','name','code']);
        return view('fixed-assets.create', compact('categories', 'currency', 'branches'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'                 => 'required|string|max:255',
            'category_id'          => 'required|exists:fixed_asset_categories,id',
            'branch_id'            => 'nullable|exists:branches,id',
            'purchase_date'        => 'required|date',
            'purchase_cost'        => 'required|numeric|min:0.01',
            'residual_value'       => 'required|numeric|min:0',
            'useful_life_months'   => 'required|integer|min:1',
            'depreciation_method'  => 'required|in:straight_line,declining_balance',
            'depreciation_rate'    => 'nullable|numeric|min:0.0001|max:1',
            'supplier_name'        => 'nullable|string|max:255',
            'payment_method'       => 'required|in:cash,bank,credit',
            'notes'                => 'nullable|string',
        ]);

        if ($request->residual_value >= $request->purchase_cost) {
            return back()->withInput()
                ->with('error', 'القيمة التخريدية يجب أن تكون أقل من تكلفة الشراء');
        }

        DB::beginTransaction();
        try {
            $asset = FixedAsset::create([
                'name'                  => $request->name,
                'description'           => $request->description,
                'category_id'           => $request->category_id,
                'branch_id'             => $request->branch_id
                                           ?? auth()->user()->branch_id
                                           ?? Setting::get('default_branch_id'),
                'user_id'               => auth()->id(),
                'purchase_date'         => $request->purchase_date,
                'purchase_cost'         => $request->purchase_cost,
                'residual_value'        => $request->residual_value,
                'supplier_name'         => $request->supplier_name,
                'depreciation_method'   => $request->depreciation_method,
                'useful_life_months'    => $request->useful_life_months,
                'depreciation_rate'     => $request->depreciation_rate ?: null,
                'notes'                 => $request->notes,
            ]);

            // ترحيل قيد شراء الأصل دائمًا — نقدًا/بنكًا (دائن نقد/بنك) أو آجلًا (دائن ذمم دائنة)
            $entry = (new LedgerPostingService())->postAssetPurchase(
                $asset->load('category.assetAccount'),
                $request->payment_method
            );
            $asset->update(['journal_entry_id' => $entry->id]);

            DB::commit();

            return redirect()->route('fixed-assets.show', $asset)
                ->with('success', 'تم تسجيل الأصل الثابت — ' . $asset->asset_code);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'خطأ: ' . $e->getMessage());
        }
    }

    public function show(FixedAsset $fixedAsset)
    {
        $fixedAsset->load('category', 'branch', 'user', 'journalEntry',
            'depreciationEntries.journalEntry');
        $currency = Setting::get('currency_symbol', 'ج.م');
        $schedule = DepreciationService::buildSchedule($fixedAsset);

        // Mark already-posted periods
        $posted = $fixedAsset->depreciationEntries
            ->map(fn($e) => $e->period_year . '-' . $e->period_month)
            ->toArray();

        return view('fixed-assets.show', compact('fixedAsset', 'currency', 'schedule', 'posted'));
    }

    /** Show the depreciation form for a single asset */
    public function depreciateForm(FixedAsset $fixedAsset)
    {
        if ($fixedAsset->status !== 'active') {
            return redirect()->route('fixed-assets.show', $fixedAsset)
                ->with('error', 'الأصل غير نشط');
        }
        $currency = Setting::get('currency_symbol', 'ج.م');
        return view('fixed-assets.depreciate', compact('fixedAsset', 'currency'));
    }

    /** Run depreciation for a single asset / specific period */
    public function depreciate(Request $request, FixedAsset $fixedAsset)
    {
        $request->validate([
            'period_year'  => 'required|integer|min:2000|max:2100',
            'period_month' => 'required|integer|min:1|max:12',
        ]);

        $entry = DepreciationService::runForAsset(
            $fixedAsset,
            (int) $request->period_year,
            (int) $request->period_month
        );

        if (!$entry) {
            return redirect()->route('fixed-assets.show', $fixedAsset)
                ->with('warning', 'لا يوجد استهلاك لهذه الفترة (مرحَّل مسبقاً أو لا يوجد مبلغ)');
        }

        $currency = Setting::get('currency_symbol', 'ج.م');
        return redirect()->route('fixed-assets.show', $fixedAsset)
            ->with('success', 'تم ترحيل قيد الاستهلاك — '
                . number_format($entry->depreciation_amount, 2) . ' ' . $currency);
    }

    /** Run depreciation for ALL active assets for the current month */
    public function depreciateAll(Request $request)
    {
        $request->validate([
            'period_year'  => 'required|integer|min:2000|max:2100',
            'period_month' => 'required|integer|min:1|max:12',
        ]);

        $count = DepreciationService::runAllForPeriod(
            (int) $request->period_year,
            (int) $request->period_month
        );

        return redirect()->route('fixed-assets.index')
            ->with('success', "تم ترحيل استهلاك {$count} أصل بنجاح");
    }

    /** Show disposal form */
    public function disposeForm(FixedAsset $fixedAsset)
    {
        if ($fixedAsset->status === 'disposed') {
            return redirect()->route('fixed-assets.show', $fixedAsset)
                ->with('error', 'الأصل مُستبعَد بالفعل');
        }
        $currency = Setting::get('currency_symbol', 'ج.م');
        return view('fixed-assets.dispose', compact('fixedAsset', 'currency'));
    }

    /** Process disposal */
    public function dispose(Request $request, FixedAsset $fixedAsset)
    {
        $request->validate([
            'disposal_date'   => 'required|date',
            'disposal_amount' => 'required|numeric|min:0',
            'payment_method'  => 'required|in:cash,bank',
            'notes'           => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $fixedAsset->update([
                'disposal_date'   => $request->disposal_date,
                'disposal_amount' => $request->disposal_amount,
                'status'          => 'disposed',
                'notes'           => $request->notes,
            ]);

            (new LedgerPostingService())->postAssetDisposal(
                $fixedAsset->fresh()->load('category'),
                (float) $request->disposal_amount,
                $request->payment_method
            );

            DB::commit();

            return redirect()->route('fixed-assets.show', $fixedAsset)
                ->with('success', 'تم استبعاد الأصل وترحيل القيد المحاسبي');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'خطأ: ' . $e->getMessage());
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function statusBadge(string $status): string
    {
        return match($status) {
            'active'            => "<span class='badge bg-success'>نشط</span>",
            'fully_depreciated' => "<span class='badge bg-warning text-dark'>مستهلك</span>",
            'disposed'          => "<span class='badge bg-secondary'>مُستبعَد</span>",
            default             => "<span class='badge bg-light text-dark'>{$status}</span>",
        };
    }

    private function actionButtons(FixedAsset $a): string
    {
        /** @var \App\Models\User|null $u */
        $u    = auth()->user();
        $show = '<a href="'.route('fixed-assets.show', $a).'" class="btn btn-sm btn-info btn-action" title="عرض"><i class="bi bi-eye"></i></a>';
        $dep  = '';
        $dis  = '';

        if ($a->status === 'active') {
            if ($u?->can('fixed_assets.depreciate')) {
                $dep = '<a href="'.route('fixed-assets.depreciate-form', $a).'" class="btn btn-sm btn-warning btn-action" title="استهلاك"><i class="bi bi-calendar-minus"></i></a>';
            }
            if ($u?->can('fixed_assets.dispose')) {
                $dis = '<a href="'.route('fixed-assets.dispose-form', $a).'" class="btn btn-sm btn-outline-danger btn-action" title="استبعاد"><i class="bi bi-trash3"></i></a>';
            }
        }

        return '<div class="d-flex gap-1 flex-nowrap">'.$show.$dep.$dis.'</div>';
    }
}
