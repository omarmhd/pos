<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\EosbProvision;
use App\Models\Setting;
use App\Services\LedgerPostingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EosbController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:hr.eosb.view')->only(['index', 'preview']);
        $this->middleware('can:hr.eosb.post')->only(['post']);
    }

    /** List all posted EOSB provision runs */
    public function index(Request $request)
    {
        $branchId     = $this->effectiveBranchId($request);
        $branchLocked = $this->isBranchLocked();
        $branches     = Branch::where('is_active', true)->orderBy('name')->get(['id','name','code']);
        $currency     = Setting::get('currency_symbol', 'ج.م');

        // Summarise by period
        $runs = EosbProvision::query()
            ->select('period_year', 'period_month', 'journal_entry_id',
                     DB::raw('COUNT(*) as employee_count'),
                     DB::raw('SUM(provision_amount) as total_provision'),
                     DB::raw('MAX(created_at) as posted_at'))
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->groupBy('period_year', 'period_month', 'journal_entry_id')
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->paginate(24)
            ->withQueryString();

        // Check if current month already posted
        $alreadyPosted = EosbProvision::where('period_year',  now()->year)
                                      ->where('period_month', now()->month)
                                      ->exists();

        return view('hr.eosb.index', compact(
            'runs', 'branches', 'branchId', 'branchLocked',
            'currency', 'alreadyPosted'
        ));
    }

    /**
     * Preview EOSB for a month — calculate without saving.
     * Shows per-employee breakdown: service months, salary, provision.
     */
    public function preview(Request $request)
    {
        $year  = (int) $request->input('year',  now()->year);
        $month = (int) $request->input('month', now()->month);

        $alreadyPosted = EosbProvision::where('period_year', $year)
                                      ->where('period_month', $month)
                                      ->exists();

        $periodEnd = Carbon::create($year, $month, 1)->endOfMonth();
        $currency  = Setting::get('currency_symbol', 'ج.م');

        // Parentheses are critical: without them, orWhhere breaks is_active filter
        // Bad:  WHERE is_active=1 AND termination IS NULL OR termination > date
        // Good: WHERE is_active=1 AND (termination IS NULL OR termination > date)
        $employees = Employee::where('is_active', true)
            ->where(fn($q) => $q->whereNull('termination_date')
                               ->orWhereDate('termination_date', '>', $periodEnd))
            ->orderBy('name')
            ->get();

        // الحساب وفق الشرائح القابلة للتخصيص (عام أو تجاوز الموظف) وأسلوب المطلوب التراكمي
        $lines = $employees->map(fn(Employee $e) =>
            (new \App\Services\EosbCalculator($e))->breakdown($year, $month)
        )->filter(fn($l) => $l['provision'] > 0);

        $totalProvision = $lines->sum('provision');

        return view('hr.eosb.preview', compact(
            'year', 'month', 'lines', 'totalProvision',
            'currency', 'alreadyPosted'
        ));
    }

    /**
     * Post EOSB provisions for a month.
     * Creates one EosbProvision row per employee + one consolidated GL entry.
     */
    public function post(Request $request)
    {
        $request->validate([
            'year'  => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $year  = (int) $request->year;
        $month = (int) $request->month;

        if (EosbProvision::where('period_year', $year)->where('period_month', $month)->exists()) {
            return back()->with('error', 'مخصصات نهاية الخدمة لهذه الفترة مُرحَّلة مسبقاً');
        }

        $periodEnd = Carbon::create($year, $month, 1)->endOfMonth();
        $branchId  = $this->effectiveBranchId($request) ?? Setting::get('default_branch_id');

        DB::beginTransaction();
        try {
            // الأقواس حاسمة: بدونها تنكسر أسبقية AND/OR ويُدرَج موظفون غير نشطين خطأً
            // الصحيح: WHERE is_active=1 AND (termination IS NULL OR termination > date)
            $employees = Employee::where('is_active', true)
                ->where(fn($q) => $q->whereNull('termination_date')
                                   ->orWhereDate('termination_date', '>', $periodEnd))
                ->get();

            $provisionRows = [];
            $totalProvision = 0.0;

            foreach ($employees as $e) {
                // الحساب وفق الشرائح وأسلوب المطلوب التراكمي (يعالج تغيّر الشريحة والراتب)
                $b = (new \App\Services\EosbCalculator($e))->breakdown($year, $month);
                if ($b['provision'] < 0.01) continue;

                $totalProvision += $b['provision'];
                $provisionRows[] = $b;
            }

            if (empty($provisionRows)) {
                DB::rollBack();
                return back()->with('error', 'لا يوجد موظفون نشطون لتسجيل مخصصاتهم');
            }

            // Post single consolidated GL entry
            $entry = (new LedgerPostingService())->postEosbProvision(
                $year, $month,
                $totalProvision,
                $provisionRows,
                $branchId ? (int) $branchId : null
            );

            // Persist per-employee rows
            $now = now();
            foreach ($provisionRows as $row) {
                EosbProvision::create([
                    'employee_id'      => $row['employee']->id,
                    'branch_id'        => $row['employee']->branch_id ?? $branchId,
                    'journal_entry_id' => $entry->id,
                    'period_year'      => $year,
                    'period_month'     => $month,
                    'base_salary'      => $row['base_salary'],
                    'service_months'   => $row['service_months'],
                    'service_years'    => $row['service_years'],
                    'provision_amount' => $row['provision'],
                    'cumulative_amount'=> $row['cumulative'],
                    'status'           => 'posted',
                ]);
            }

            DB::commit();

            $currency  = Setting::get('currency_symbol', 'ج.م');
            $months = [1=>'يناير',2=>'فبراير',3=>'مارس',4=>'أبريل',
                       5=>'مايو',6=>'يونيو',7=>'يوليو',8=>'أغسطس',
                       9=>'سبتمبر',10=>'أكتوبر',11=>'نوفمبر',12=>'ديسمبر'];

            return redirect()->route('hr.eosb.index')
                ->with('success', 'تم ترحيل مخصصات نهاية الخدمة لـ ' . ($months[$month] ?? $month)
                    . ' ' . $year . ' — إجمالي: '
                    . number_format($totalProvision, 2) . ' ' . $currency
                    . ' لـ ' . count($provisionRows) . ' موظف'
                    . ' — قيد: ' . $entry->entry_number);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'خطأ: ' . $e->getMessage());
        }
    }
}
