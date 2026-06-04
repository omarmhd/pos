<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BudgetController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:financial_statements.view')->only(['index','show','variance']);
        $this->middleware('can:accounting.post')->only(['create','store','edit','update']);
    }

    public function index()
    {
        $budgets  = Budget::with('branch','createdBy')->orderByDesc('year')->get();
        $currency = Setting::get('currency_symbol', 'ج.م');
        return view('accounting.budgets.index', compact('budgets', 'currency'));
    }

    public function create()
    {
        $branches = Branch::where('is_active', true)->orderBy('name')->get(['id','name','code']);
        $accounts = Account::where('is_active', true)->where('is_header', false)
            ->whereIn('type', ['revenue','expense'])
            ->orderBy('code')->get(['id','code','name','type']);
        return view('accounting.budgets.create', compact('branches', 'accounts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'      => 'required|string|max:100',
            'year'      => 'required|integer|min:2020|max:2100',
            'branch_id' => 'nullable|exists:branches,id',
            'lines'     => 'required|array|min:1',
            'lines.*.account_id' => 'required|exists:accounts,id',
            'lines.*.annual'     => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($request) {
            $budget = Budget::create([
                'name'       => $request->name,
                'year'       => $request->year,
                'branch_id'  => $request->branch_id ?: null,
                'created_by' => auth()->id(),
                'is_active'  => true,
            ]);

            foreach ($request->lines as $line) {
                if ((float) $line['annual'] == 0) continue;
                $bl = new BudgetLine(['budget_id' => $budget->id, 'account_id' => $line['account_id']]);
                $bl->spreadAnnual((float) $line['annual']);
                $bl->save();
            }
        });

        return redirect()->route('budgets.index')->with('success', 'تم إنشاء الموازنة بنجاح');
    }

    public function show(Budget $budget)
    {
        $budget->load('lines.account', 'branch');
        $currency = Setting::get('currency_symbol', 'ج.م');
        return view('accounting.budgets.show', compact('budget', 'currency'));
    }

    /**
     * Budget vs Actual variance report.
     * Compares monthly budget lines against actual GL activity.
     */
    public function variance(Request $request, Budget $budget)
    {
        $budget->load('lines.account');
        $month    = (int) $request->input('month', now()->month);
        $currency = Setting::get('currency_symbol', 'ج.م');

        $from = Carbon::create($budget->year, $month, 1)->startOfDay();
        $to   = Carbon::create($budget->year, $month, 1)->endOfMonth()->endOfDay();

        // Get actual GL balances for the month
        $actuals = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'jel.journal_entry_id', '=', 'je.id')
            ->whereBetween('je.entry_date', [$from->toDateString(), $to->toDateString()])
            ->when($budget->branch_id, fn($q) => $q->where('je.branch_id', $budget->branch_id))
            ->selectRaw('jel.account_id, SUM(jel.debit) as total_debit, SUM(jel.credit) as total_credit')
            ->groupBy('jel.account_id')
            ->get()
            ->keyBy('account_id');

        $rows = $budget->lines->map(function (BudgetLine $line) use ($month, $actuals) {
            $budgeted = $line->budgetForMonth($month);
            $act      = $actuals->get($line->account_id);
            $isExp    = $line->account?->type === 'expense';
            $actual   = $act
                ? ($isExp ? (float)$act->total_debit - (float)$act->total_credit
                          : (float)$act->total_credit - (float)$act->total_debit)
                : 0.0;
            $variance = $actual - $budgeted;
            $pct      = $budgeted > 0 ? round($actual / $budgeted * 100, 1) : null;

            return [
                'account'  => $line->account,
                'budgeted' => $budgeted,
                'actual'   => round($actual, 2),
                'variance' => round($variance, 2),
                'pct'      => $pct,
                'over'     => $isExp ? ($variance > 0) : ($variance < 0),
            ];
        });

        return view('accounting.budgets.variance', compact(
            'budget', 'rows', 'month', 'currency',
        ));
    }
}
