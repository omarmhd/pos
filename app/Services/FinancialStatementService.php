<?php

namespace App\Services;

use App\Models\Account;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialStatementService
{
    /**
     * Cumulative account balance from the beginning of time up to $asOf.
     *
     * @param int|null $branchId  null = consolidated (all branches)
     */
    public function getAccountBalance(Account $account, ?Carbon $asOf = null, ?int $branchId = null): float
    {
        $query = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entry_lines.account_id', $account->id)
            ->when($branchId, fn($q) => $q->where('journal_entries.branch_id', $branchId));

        if ($asOf) {
            $query->whereDate('journal_entries.entry_date', '<=', $asOf->toDateString());
        }

        $row = $query->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')->first();

        $debit  = (float) ($row->total_debit  ?? 0);
        $credit = (float) ($row->total_credit ?? 0);

        return match ($account->type) {
            'asset', 'expense'               => $debit - $credit,
            'liability', 'equity', 'revenue' => $credit - $debit,
            default                          => 0.0,
        };
    }

    /**
     * Account balance for a specific period.
     *
     * @param int|null $branchId  null = consolidated
     */
    public function getPeriodBalance(Account $account, Carbon $from, Carbon $to, ?int $branchId = null): float
    {
        $row = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entry_lines.account_id', $account->id)
            ->whereBetween('journal_entries.entry_date', [$from->toDateString(), $to->toDateString()])
            ->when($branchId, fn($q) => $q->where('journal_entries.branch_id', $branchId))
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();

        $debit  = (float) ($row->total_debit  ?? 0);
        $credit = (float) ($row->total_credit ?? 0);

        return match ($account->type) {
            'asset', 'expense'               => $debit - $credit,
            'liability', 'equity', 'revenue' => $credit - $debit,
            default                          => 0.0,
        };
    }

    /**
     * Income Statement for the period [from, to].
     *
     * @param Carbon   $from
     * @param Carbon   $to
     * @param int|null $branchId  null = consolidated (all branches)
     */
    public function getIncomeStatement(Carbon $from, Carbon $to, ?int $branchId = null): array
    {
        $accounts = Account::where('is_active', true)
            ->where('is_header', false)
            ->whereIn('type', ['revenue', 'expense'])
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        $balances = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'jel.journal_entry_id', '=', 'je.id')
            ->whereBetween('je.entry_date', [$from->toDateString(), $to->toDateString()])
            ->when($branchId, fn($q) => $q->where('je.branch_id', $branchId))
            ->whereIn('jel.account_id', $accounts->pluck('id'))
            ->groupBy('jel.account_id')
            ->selectRaw('jel.account_id, SUM(jel.debit) as total_debit, SUM(jel.credit) as total_credit')
            ->get()
            ->keyBy('account_id');

        $grossRevenue  = [];
        $contraRevenue = [];
        $otherIncome   = [];
        $cogs          = [];
        $opex          = [];

        foreach ($accounts as $account) {
            $b      = $balances->get($account->id);
            $debit  = (float) ($b->total_debit  ?? 0);
            $credit = (float) ($b->total_credit ?? 0);
            $code   = (int) $account->code;

            if ($account->type === 'revenue') {
                $isContra = in_array($code, [4200, 4300]);
                $amount   = $isContra ? ($debit - $credit) : ($credit - $debit);
                if (abs($amount) < 0.001) continue;

                if ($isContra)                             { $contraRevenue[] = ['account' => $account, 'amount' => $amount]; }
                elseif ($code >= 4100 && $code < 4200)    { $otherIncome[]   = ['account' => $account, 'amount' => $amount]; }
                else                                       { $grossRevenue[]  = ['account' => $account, 'amount' => $amount]; }
            } else {
                $amount = $debit - $credit;
                if (abs($amount) < 0.001) continue;
                if ($code >= 5000 && $code < 6000)        { $cogs[] = ['account' => $account, 'amount' => $amount]; }
                else                                       { $opex[] = ['account' => $account, 'amount' => $amount]; }
            }
        }

        $totalGrossRevenue = collect($grossRevenue)->sum('amount');
        $totalContra       = collect($contraRevenue)->sum('amount');
        $totalNetRevenue   = $totalGrossRevenue - $totalContra;
        $totalCogs         = collect($cogs)->sum('amount');
        $grossProfit       = $totalNetRevenue - $totalCogs;
        $totalOpex         = collect($opex)->sum('amount');
        $operatingIncome   = $grossProfit - $totalOpex;
        $totalOtherIncome  = collect($otherIncome)->sum('amount');
        $netIncome         = $operatingIncome + $totalOtherIncome;
        $totalRevenue      = $totalNetRevenue;

        $grossMargin = $totalNetRevenue > 0 ? round(($grossProfit / $totalNetRevenue) * 100, 1) : 0;
        $opexRatio   = $totalNetRevenue > 0 ? round(($totalOpex   / $totalNetRevenue) * 100, 1) : 0;
        $netMargin   = $totalNetRevenue > 0 ? round(($netIncome   / $totalNetRevenue) * 100, 1) : 0;

        return compact(
            'grossRevenue', 'contraRevenue', 'otherIncome',
            'cogs', 'opex',
            'totalGrossRevenue', 'totalContra', 'totalNetRevenue',
            'totalCogs', 'grossProfit',
            'totalOpex', 'operatingIncome',
            'totalOtherIncome', 'netIncome',
            'totalRevenue', 'grossMargin', 'opexRatio', 'netMargin'
        );
    }

    /**
     * صافي الدخل غير المُقفَل حتى تاريخ معيّن (Unclosed retained net income).
     *
     * = مجموع (دائن − مدين) لكل حسابات الإيراد والمصروف حتى التاريخ.
     * بعد قيد الإقفال السنوي تُصفَّر هذه الحسابات (يُرحَّل الصافي للأرباح المحتجزة)،
     * فيعكس هذا الرقم فقط الأرباح/الخسائر غير المُقفَلة — وهو ما يجب إضافته لحقوق
     * الملكية لتتوازن الميزانية بصرف النظر عن السنة المالية أو حالة الإقفال.
     *
     * البرهان (القيد المزدوج): الأصول = الخصوم + حقوق الملكية + هذا الرقم.
     */
    public function getUnclosedNetIncome(Carbon $asOf, ?int $branchId = null): float
    {
        $plAccountIds = Account::whereIn('type', ['revenue', 'expense'])->pluck('id');
        if ($plAccountIds->isEmpty()) {
            return 0.0;
        }

        $row = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'jel.journal_entry_id', '=', 'je.id')
            ->whereDate('je.entry_date', '<=', $asOf->toDateString())
            ->when($branchId, fn($q) => $q->where('je.branch_id', $branchId))
            ->whereIn('jel.account_id', $plAccountIds)
            ->selectRaw('SUM(jel.credit) as c, SUM(jel.debit) as d')
            ->first();

        return round((float) ($row->c ?? 0) - (float) ($row->d ?? 0), 2);
    }

    /**
     * Balance Sheet as of $asOf.
     *
     * Note: The Balance Sheet is typically consolidated (company-wide).
     * Branch filtering is supported but balance may not reconcile
     * if inter-branch accounts are used.
     *
     * @param int|null $branchId  null = consolidated
     */
    public function getBalanceSheet(Carbon $asOf, float $netIncome = 0.0, ?int $branchId = null): array
    {
        $accounts = Account::where('is_active', true)
            ->whereIn('type', ['asset', 'liability', 'equity'])
            ->where('is_header', false)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'sub_type']);

        $balances = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'jel.journal_entry_id', '=', 'je.id')
            ->whereDate('je.entry_date', '<=', $asOf->toDateString())
            ->when($branchId, fn($q) => $q->where('je.branch_id', $branchId))
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

            if (abs($balance) < 0.001) continue;

            $code    = (int) $account->code;
            $subType = $account->sub_type;

            if ($account->type === 'asset') {
                $isFixed = $subType === 'fixed_asset' || ($subType === null && $code >= 1500);
                if ($isFixed) { $fixedAssets[]   = ['account' => $account, 'amount' => $balance]; }
                else          { $currentAssets[] = ['account' => $account, 'amount' => $balance]; }
            } elseif ($account->type === 'liability') {
                $isLongTerm = $subType === 'long_term_liability' || ($subType === null && $code >= 2500);
                if ($isLongTerm) { $longTermLiabilities[] = ['account' => $account, 'amount' => $balance]; }
                else             { $currentLiabilities[]  = ['account' => $account, 'amount' => $balance]; }
            } elseif ($account->type === 'equity') {
                $equityAccounts[] = ['account' => $account, 'amount' => $balance];
            }
        }

        $totalCurrentAssets       = collect($currentAssets)->sum('amount');
        $totalFixedAssets         = collect($fixedAssets)->sum('amount');
        $totalAssets              = $totalCurrentAssets + $totalFixedAssets;
        $totalCurrentLiabilities  = collect($currentLiabilities)->sum('amount');
        $totalLongTermLiabilities = collect($longTermLiabilities)->sum('amount');
        $totalLiabilities         = $totalCurrentLiabilities + $totalLongTermLiabilities;
        $totalEquityAccounts      = collect($equityAccounts)->sum('amount');
        $totalEquity              = $totalEquityAccounts + $netIncome;
        $totalLiabAndEquity       = $totalLiabilities + $totalEquity;
        $isBalanced               = abs($totalAssets - $totalLiabAndEquity) < 0.01;
        $difference               = $totalAssets - $totalLiabAndEquity;

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
