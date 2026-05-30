<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrialBalanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:trial_balance.view');
    }

    public function index(Request $request)
    {
        $asOf = $request->filled('as_of')
            ? Carbon::parse($request->input('as_of'))->endOfDay()
            : now()->endOfDay();

        // One JOIN query — no N+1
        $raw = DB::table('accounts as a')
            ->leftJoin('journal_entry_lines as jel', 'a.id', '=', 'jel.account_id')
            ->leftJoin('journal_entries as je', function ($join) use ($asOf) {
                $join->on('jel.journal_entry_id', '=', 'je.id')
                     ->whereDate('je.entry_date', '<=', $asOf->toDateString());
            })
            ->where('a.is_active', true)
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type')
            ->orderBy('a.code')
            ->selectRaw('a.id, a.code, a.name, a.type,
                         COALESCE(SUM(jel.debit),  0) as raw_debit,
                         COALESCE(SUM(jel.credit), 0) as raw_credit')
            ->get();

        $totalDebitCol  = 0.0;
        $totalCreditCol = 0.0;

        $rows = $raw
            ->filter(fn($r) => (float)$r->raw_debit > 0 || (float)$r->raw_credit > 0)
            ->map(function ($r) use (&$totalDebitCol, &$totalCreditCol) {
                $creditNormal = in_array($r->type, ['liability', 'equity', 'revenue']);
                $net = $creditNormal
                    ? ((float)$r->raw_credit - (float)$r->raw_debit)
                    : ((float)$r->raw_debit  - (float)$r->raw_credit);

                // Positive net → normal side; negative → abnormal (opposite column)
                if ($net >= 0) {
                    $debitCol  = $creditNormal ? 0.0 : $net;
                    $creditCol = $creditNormal ? $net : 0.0;
                } else {
                    $debitCol  = $creditNormal ? abs($net) : 0.0;
                    $creditCol = $creditNormal ? 0.0 : abs($net);
                }

                $totalDebitCol  += $debitCol;
                $totalCreditCol += $creditCol;

                return (object)[
                    'code'       => $r->code,
                    'name'       => $r->name,
                    'type'       => $r->type,
                    'debit_col'  => $debitCol,
                    'credit_col' => $creditCol,
                    'abnormal'   => $net < 0,
                ];
            })
            ->values();

        $isBalanced = abs($totalDebitCol - $totalCreditCol) < 0.01;

        return view('accounting.trial_balance', compact(
            'rows', 'totalDebitCol', 'totalCreditCol', 'asOf', 'isBalanced'
        ));
    }
}
