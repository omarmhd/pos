<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Setting;
use App\Services\FinancialStatementService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ConsolidatedReportController extends Controller
{
    public function __construct(private FinancialStatementService $fs)
    {
        $this->middleware('can:financial_statements.view');
    }

    /**
     * Consolidated P&L — one column per active branch + Total column.
     * Based on the same getIncomeStatement() used by the single-branch view,
     * just called once per branch_id and once with null (all branches = Total).
     */
    public function incomeStatement(Request $request)
    {
        $from = Carbon::parse($request->input('from', now()->startOfYear()->toDateString()));
        $to   = Carbon::parse($request->input('to',   now()->toDateString()));

        $branches = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        $currency = Setting::get('currency_symbol', 'ج.م');

        // Build one P&L per branch
        $columns = [];
        foreach ($branches as $b) {
            $columns[$b->id] = [
                'branch' => $b,
                'data'   => $this->fs->getIncomeStatement($from, $to, $b->id),
            ];
        }

        // Consolidated total (all branches)
        $consolidated = $this->fs->getIncomeStatement($from, $to, null);

        // Collect all unique account codes across all branches (for table rows)
        $allAccounts = collect();
        foreach ($columns as $col) {
            foreach (['grossRevenue','contraRevenue','otherIncome','cogs','opex'] as $section) {
                foreach ($col['data'][$section] as $row) {
                    $allAccounts->put($row['account']->code, $row['account']);
                }
            }
        }
        $allAccounts = $allAccounts->sortKeys();

        return view('accounting.consolidated_pl', compact(
            'from', 'to', 'branches', 'columns', 'consolidated',
            'allAccounts', 'currency'
        ));
    }
}
