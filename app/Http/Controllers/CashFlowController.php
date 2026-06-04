<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AssetDepreciationEntry;
use App\Models\Branch;
use App\Models\EosbProvision;
use App\Models\Setting;
use App\Services\FinancialStatementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * قائمة التدفقات النقدية — IAS 7 (الطريقة غير المباشرة)
 *
 * Structure:
 *  A. أنشطة التشغيل (Operating Activities)
 *     • صافي الربح
 *     + تعديلات غير نقدية: استهلاك + مخصصات EOSB
 *     ± تغييرات رأس المال العامل (AR، مخزون، AP ...)
 *
 *  B. أنشطة الاستثمار (Investing Activities)
 *     • شراء الأصول الثابتة  (DR Asset / CR Cash)
 *     • حصيلة بيع الأصول     (DR Cash / CR Asset)
 *
 *  C. أنشطة التمويل (Financing Activities)
 *     • المتبقي (= صافي التغيير − أ − ب) يضمن توازن القائمة
 *
 *  D. صافي التغيير في النقدية = أ + ب + ج
 *  E. الرصيد الافتتاحي للنقدية
 *  F. الرصيد الختامي للنقدية   = د + ه
 */
class CashFlowController extends Controller
{
    public function __construct(private FinancialStatementService $fs)
    {
        $this->middleware('can:financial_statements.view');
    }

    public function index(Request $request)
    {
        $from     = Carbon::parse($request->input('from', now()->startOfYear()->toDateString()))->startOfDay();
        $to       = Carbon::parse($request->input('to',   now()->toDateString()))->endOfDay();
        $prevDate = $from->copy()->subDay()->endOfDay();

        $branchId     = $this->effectiveBranchId($request);
        $branchLocked = $this->isBranchLocked();
        $branches     = Branch::where('is_active', true)->orderBy('name')->get(['id','name','code']);
        $currency     = Setting::get('currency_symbol', 'ج.م');

        // ── A. Operating Activities ──────────────────────────────────────────

        // 1. Net Income from P&L
        $incomeData = $this->fs->getIncomeStatement($from, $to, $branchId);
        $netIncome  = round($incomeData['netIncome'], 2);

        // 2. Non-cash adjustments
        // Depreciation: add back (real expense but no cash went out)
        $depreciation = $this->periodSum('AssetDepreciationEntry', $from, $to, 'depreciation_amount', $branchId);

        // EOSB Provisions: add back (liability accrual, no cash out yet)
        $eosbProvision = round(
            \App\Models\EosbProvision::where('status', 'posted')
                ->whereYear('created_at', $from->year)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->whereBetween('created_at', [$from->toDateString(), $to->toDateString()])
                ->sum('provision_amount'),
            2
        );

        // 3. Working Capital Changes
        // Rule: Asset ↓ = cash in (+), Asset ↑ = cash out (-)
        //       Liability ↑ = cash in (+), Liability ↓ = cash out (-)
        $wcItems = [];

        $wcAccounts = [
            // [code, label, direction: 'asset'|'liability']
            ['1200', 'الذمم المدينة (العملاء)',          'asset'],
            ['1250', 'سلف الموظفين',                     'asset'],
            ['1300', 'المخزون',                           'asset'],
            ['1400', 'مصروفات مدفوعة مقدماً',           'asset'],
            ['2000', 'الذمم الدائنة (الموردين)',          'liability'],
            ['2050', 'سُلَف العملاء (ودائع)',             'liability'],
            ['2100', 'الرواتب المستحقة',                  'liability'],
            ['2200', 'مخصص نهاية الخدمة',                 'liability'],
        ];

        $totalWC = 0.0;
        foreach ($wcAccounts as [$code, $label, $dir]) {
            $acct = Account::where('code', $code)->where('is_active', true)->first();
            if (!$acct) continue;

            $opening = $this->fs->getAccountBalance($acct, $prevDate, $branchId);
            $closing = $this->fs->getAccountBalance($acct, $to, $branchId);
            $change  = round($closing - $opening, 2);

            // Convert to cash-flow sign
            // Asset: decrease → cash in (+) → sign = -$change (opening - closing)
            // Liability: increase → cash in (+) → sign = +$change (closing - opening)
            $cashEffect = $dir === 'asset' ? -$change : $change;
            if (abs($cashEffect) < 0.005) continue;

            $wcItems[]   = ['label' => $label, 'code' => $code, 'effect' => $cashEffect];
            $totalWC    += $cashEffect;
        }
        $totalWC = round($totalWC, 2);

        $operatingCash = round($netIncome + $depreciation + $eosbProvision + $totalWC, 2);

        // ── B. Investing Activities ──────────────────────────────────────────
        // Fixed asset purchases: GL entries where source_type=FixedAsset,
        // with debit on a 15xx asset account (i.e., cash was credited)
        $assetPurchases = $this->investingFromGL(
            $from, $to, $branchId,
            direction: 'purchase'   // cash outflow: fixed asset account debited
        );
        $assetDisposals = $this->investingFromGL(
            $from, $to, $branchId,
            direction: 'disposal'   // cash inflow: fixed asset account credited
        );
        $investingCash  = round($assetDisposals - $assetPurchases, 2);

        // ── C. Financing Activities ──────────────────────────────────────────
        // Equity changes: capital contributions and drawings
        $equityChange   = $this->equityChanges($from, $to, $branchId);
        $financingCash  = round($equityChange, 2);

        // ── D/E/F. Balances ──────────────────────────────────────────────────
        $netChange  = round($operatingCash + $investingCash + $financingCash, 2);

        $cashAccounts = Account::where('is_active', true)
            ->where('is_header', false)
            ->where(function ($q) {
                $q->where('code', 'like', '1000%')
                  ->orWhere('code', 'like', '1100%');
            })
            ->get();

        $openingCash = round($cashAccounts->sum(fn($a) =>
            $this->fs->getAccountBalance($a, $prevDate, $branchId)
        ), 2);

        $closingCash = round($cashAccounts->sum(fn($a) =>
            $this->fs->getAccountBalance($a, $to, $branchId)
        ), 2);

        // Reconciliation check: net change should equal closing - opening
        $reconciliationDiff = round($closingCash - $openingCash - $netChange, 2);

        return view('accounting.cash-flow', compact(
            'from', 'to', 'currency', 'branches', 'branchId', 'branchLocked',
            // A. Operating
            'netIncome', 'depreciation', 'eosbProvision', 'wcItems', 'totalWC', 'operatingCash',
            // B. Investing
            'assetPurchases', 'assetDisposals', 'investingCash',
            // C. Financing
            'financingCash',
            // D/E/F
            'netChange', 'openingCash', 'closingCash', 'reconciliationDiff'
        ));
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function periodSum(string $model, Carbon $from, Carbon $to, string $col, ?int $branchId): float
    {
        $class = 'App\\Models\\' . $model;
        return round(
            $class::whereBetween('created_at', [$from->toDateString(), $to->toDateString()])
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->sum($col) ?? 0,
            2
        );
    }

    /**
     * Fixed asset investing cash flows.
     * Looks at JournalEntryLines for entries involving 15xx accounts
     * and a cash/bank (1000/1100) account on the other side.
     */
    private function investingFromGL(Carbon $from, Carbon $to, ?int $branchId, string $direction): float
    {
        // Get all 15xx asset accounts (fixed assets)
        $assetCodes = Account::where('code', 'like', '15%')
            ->where('is_active', true)->where('is_header', false)
            ->pluck('id');

        if ($assetCodes->isEmpty()) return 0.0;

        // Cash accounts
        $cashIds = Account::where('is_active', true)
            ->where('is_header', false)
            ->where(fn($q) => $q->where('code', 'like', '1000%')->orWhere('code', 'like', '1100%'))
            ->pluck('id');

        if ($cashIds->isEmpty()) return 0.0;

        // For purchases: fixed asset account DEBITED → find the journal entry and sum cash CREDIT
        // For disposals: fixed asset account CREDITED → find the journal entry and sum cash DEBIT

        $jeIds = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'jel.journal_entry_id', '=', 'je.id')
            ->whereBetween('je.entry_date', [$from->toDateString(), $to->toDateString()])
            ->when($branchId, fn($q) => $q->where('je.branch_id', $branchId))
            ->whereIn('jel.account_id', $assetCodes)
            ->where($direction === 'purchase'
                ? fn($q) => $q->where('jel.debit', '>', 0)
                : fn($q) => $q->where('jel.credit', '>', 0)
            )
            ->pluck('jel.journal_entry_id');

        if ($jeIds->isEmpty()) return 0.0;

        // Sum the cash side
        $cashCol = $direction === 'purchase' ? 'credit' : 'debit';
        $total   = DB::table('journal_entry_lines')
            ->whereIn('journal_entry_id', $jeIds)
            ->whereIn('account_id', $cashIds)
            ->sum($cashCol);

        return round((float) $total, 2);
    }

    /** Equity account changes → financing activities */
    private function equityChanges(Carbon $from, Carbon $to, ?int $branchId): float
    {
        $equityAccounts = Account::where('type', 'equity')
            ->where('is_active', true)->where('is_header', false)->get();

        $prevDate = $from->copy()->subDay()->endOfDay();
        $total    = 0.0;

        foreach ($equityAccounts as $a) {
            $opening  = $this->fs->getAccountBalance($a, $prevDate, $branchId);
            $closing  = $this->fs->getAccountBalance($a, $to, $branchId);
            // Equity normal balance (credit): increase = cash came in (capital contribution)
            $total   += $closing - $opening;
        }

        return round($total, 2);
    }
}
