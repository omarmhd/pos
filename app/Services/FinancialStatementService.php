<?php

namespace App\Services;

use App\Models\Account;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialStatementService
{
    /**
     * Cumulative account balance from the beginning of time up to $asOf.
     * Respects normal balance convention.
     */
    public function getAccountBalance(Account $account, ?Carbon $asOf = null): float
    {
        $query = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entry_lines.account_id', $account->id);

        if ($asOf) {
            $query->whereDate('journal_entries.entry_date', '<=', $asOf->toDateString());
        }

        $row = $query->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')->first();

        $debit  = (float) ($row->total_debit  ?? 0);
        $credit = (float) ($row->total_credit ?? 0);

        return match ($account->type) {
            'asset', 'expense'              => $debit - $credit,
            'liability', 'equity', 'revenue' => $credit - $debit,
            default                          => 0.0,
        };
    }

    /**
     * Account balance for a specific period.
     */
    public function getPeriodBalance(Account $account, Carbon $from, Carbon $to): float
    {
        $row = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entry_lines.account_id', $account->id)
            ->whereBetween('journal_entries.entry_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();

        $debit  = (float) ($row->total_debit  ?? 0);
        $credit = (float) ($row->total_credit ?? 0);

        return match ($account->type) {
            'asset', 'expense'              => $debit - $credit,
            'liability', 'equity', 'revenue' => $credit - $debit,
            default                          => 0.0,
        };
    }

    /**
     * Income Statement for the period [from, to].
     * Single GROUP BY query replaces N+1 getPeriodBalance() calls.
     */
    public function getIncomeStatement(Carbon $from, Carbon $to): array
    {
        $accounts = Account::where('is_active', true)
            ->whereIn('type', ['revenue', 'expense'])
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        $balances = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'jel.journal_entry_id', '=', 'je.id')
            ->whereBetween('je.entry_date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('jel.account_id', $accounts->pluck('id'))
            ->groupBy('jel.account_id')
            ->selectRaw('jel.account_id, SUM(jel.debit) as total_debit, SUM(jel.credit) as total_credit')
            ->get()
            ->keyBy('account_id');

        $revenue = [];
        $cogs    = [];
        $opex    = [];

        foreach ($accounts as $account) {
            $b      = $balances->get($account->id);
            $debit  = (float) ($b->total_debit  ?? 0);
            $credit = (float) ($b->total_credit ?? 0);

            $amount = $account->type === 'revenue'
                ? $credit - $debit
                : $debit - $credit;

            if (abs($amount) < 0.001) {
                continue;
            }

            if ($account->type === 'revenue') {
                $revenue[] = ['account' => $account, 'amount' => $amount];
            } else {
                $code = (int) $account->code;
                if ($code >= 5000 && $code < 6000) {
                    $cogs[] = ['account' => $account, 'amount' => $amount];
                } else {
                    $opex[] = ['account' => $account, 'amount' => $amount];
                }
            }
        }

        $totalRevenue = collect($revenue)->sum('amount');
        $totalCogs    = collect($cogs)->sum('amount');
        $grossProfit  = $totalRevenue - $totalCogs;
        $totalOpex    = collect($opex)->sum('amount');
        $netIncome    = $grossProfit - $totalOpex;
        $grossMargin  = $totalRevenue > 0 ? round(($grossProfit / $totalRevenue) * 100, 1) : 0;
        $netMargin    = $totalRevenue > 0 ? round(($netIncome   / $totalRevenue) * 100, 1) : 0;

        return compact(
            'revenue', 'cogs', 'opex',
            'totalRevenue', 'totalCogs', 'grossProfit',
            'totalOpex', 'netIncome', 'grossMargin', 'netMargin'
        );
    }

    /**
     * Balance Sheet as of $asOf.
     * Single GROUP BY query replaces N+1 getAccountBalance() calls.
     */
    public function getBalanceSheet(Carbon $asOf, float $netIncome = 0.0): array
    {
        $accounts = Account::where('is_active', true)
            ->whereIn('type', ['asset', 'liability', 'equity'])
            ->where('is_header', false)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'sub_type']);

        $balances = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'jel.journal_entry_id', '=', 'je.id')
            ->whereDate('je.entry_date', '<=', $asOf->toDateString())
            ->whereIn('jel.account_id', $accounts->pluck('id'))
            ->groupBy('jel.account_id')
            ->selectRaw('jel.account_id, SUM(jel.debit) as total_debit, SUM(jel.credit) as total_credit')
            ->get()
            ->keyBy('account_id');

        $currentAssets       = [];
        $fixedAssets         = [];
        $currentLiabilities  = [];
        $longTermLiabilities = [];
        $equityAccounts      = [];

        foreach ($accounts as $account) {
            $b      = $balances->get($account->id);
            $debit  = (float) ($b->total_debit  ?? 0);
            $credit = (float) ($b->total_credit ?? 0);

            $balance = match ($account->type) {
                'asset'               => $debit - $credit,
                'liability', 'equity' => $credit - $debit,
                default               => 0.0,
            };

            if (abs($balance) < 0.001) {
                continue;
            }

            $code    = (int) $account->code;
            $subType = $account->sub_type;

            if ($account->type === 'asset') {
                $isFixed = $subType === 'fixed_asset'
                    || ($subType === null && $code >= 1500);
                if ($isFixed) {
                    $fixedAssets[] = ['account' => $account, 'amount' => $balance];
                } else {
                    $currentAssets[] = ['account' => $account, 'amount' => $balance];
                }
            } elseif ($account->type === 'liability') {
                $isLongTerm = $subType === 'long_term_liability'
                    || ($subType === null && $code >= 2500);
                if ($isLongTerm) {
                    $longTermLiabilities[] = ['account' => $account, 'amount' => $balance];
                } else {
                    $currentLiabilities[] = ['account' => $account, 'amount' => $balance];
                }
            } elseif ($account->type === 'equity') {
                $equityAccounts[] = ['account' => $account, 'amount' => $balance];
            }
        }

        $totalCurrentAssets      = collect($currentAssets)->sum('amount');
        $totalFixedAssets        = collect($fixedAssets)->sum('amount');
        $totalAssets             = $totalCurrentAssets + $totalFixedAssets;

        $totalCurrentLiabilities  = collect($currentLiabilities)->sum('amount');
        $totalLongTermLiabilities = collect($longTermLiabilities)->sum('amount');
        $totalLiabilities         = $totalCurrentLiabilities + $totalLongTermLiabilities;

        $totalEquityAccounts = collect($equityAccounts)->sum('amount');
        $totalEquity         = $totalEquityAccounts + $netIncome;
        $totalLiabAndEquity  = $totalLiabilities + $totalEquity;

        $isBalanced = abs($totalAssets - $totalLiabAndEquity) < 0.01;
        $difference = $totalAssets - $totalLiabAndEquity;

        return compact(
            'currentAssets', 'fixedAssets',
            'currentLiabilities', 'longTermLiabilities', 'equityAccounts',
            'totalCurrentAssets', 'totalFixedAssets', 'totalAssets',
            'totalCurrentLiabilities', 'totalLongTermLiabilities', 'totalLiabilities',
            'totalEquityAccounts', 'totalEquity', 'totalLiabAndEquity',
            'netIncome', 'isBalanced', 'difference'
        );
    }
}
