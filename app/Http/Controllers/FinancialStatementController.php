<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\Setting;
use App\Services\FinancialStatementService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FinancialStatementController extends Controller
{
    public function __construct(private FinancialStatementService $service)
    {
        $this->middleware('can:financial_statements.view');
    }

    public function incomeStatement(Request $request)
    {
        $from         = Carbon::parse($request->input('from', now()->startOfYear()->toDateString()));
        $to           = Carbon::parse($request->input('to',   now()->toDateString()));
        $branchId     = $this->effectiveBranchId($request);
        $branchLocked = $this->isBranchLocked();
        $branches     = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        $branch       = $branchId ? Branch::find($branchId) : null;

        $data = $this->service->getIncomeStatement($from, $to, $branchId);

        return view('accounting.income_statement', array_merge(
            $data,
            compact('from', 'to', 'branches', 'branchId', 'branch', 'branchLocked')
        ));
    }

    public function balanceSheet(Request $request)
    {
        $asOf         = Carbon::parse($request->input('as_of', now()->toDateString()));
        $ytdFrom      = $asOf->copy()->startOfYear();
        $branchId     = $this->effectiveBranchId($request);
        $branchLocked = $this->isBranchLocked();
        $branches     = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        $branch       = $branchId ? Branch::find($branchId) : null;

        $incomeData = $this->service->getIncomeStatement($ytdFrom, $asOf, $branchId);
        $netIncome  = $incomeData['netIncome'];

        $data = $this->service->getBalanceSheet($asOf, $netIncome, $branchId);

        // ── عملة عرض الميزانية (تحويل عرضي بسعر الصرف — مثل "عملة الميزانية" في الأصيل) ──
        $currencies        = Currency::where('is_active', true)->orderBy('code')->get();
        $displayCurrencyId = $request->input('display_currency_id');
        $displayCurrency   = $displayCurrencyId ? Currency::find($displayCurrencyId) : $currencies->firstWhere('is_base', true);
        $baseSymbol        = Setting::get('currency_symbol', 'ج.م');

        $fxRate = ($displayCurrency && !$displayCurrency->is_base && (float) $displayCurrency->exchange_rate > 0)
            ? (float) $displayCurrency->exchange_rate : 1.0;

        if ($fxRate != 1.0) {
            $data = $this->scaleBalanceSheet($data, 1 / $fxRate);
        }

        $currency = ($displayCurrency && !$displayCurrency->is_base) ? ($displayCurrency->symbol ?: $displayCurrency->code) : $baseSymbol;

        return view('accounting.balance_sheet', array_merge(
            $data,
            compact('asOf', 'ytdFrom', 'netIncome', 'branches', 'branchId', 'branch', 'branchLocked',
                    'currencies', 'displayCurrency', 'displayCurrencyId', 'currency')
        ));
    }

    /** تحويل أرقام الميزانية إلى عملة العرض (ضرب كل المبالغ والإجماليات بعامل) */
    private function scaleBalanceSheet(array $data, float $f): array
    {
        foreach (['currentAssets', 'fixedAssets', 'currentLiabilities', 'longTermLiabilities', 'equityAccounts'] as $k) {
            $data[$k] = array_map(function ($row) use ($f) {
                $row['amount'] = round((float) $row['amount'] * $f, 2);
                return $row;
            }, $data[$k] ?? []);
        }
        foreach (['totalCurrentAssets', 'totalFixedAssets', 'totalAssets',
                  'totalCurrentLiabilities', 'totalLongTermLiabilities', 'totalLiabilities',
                  'totalEquityAccounts', 'totalEquity', 'totalLiabAndEquity',
                  'netIncome', 'difference'] as $k) {
            if (isset($data[$k])) {
                $data[$k] = round((float) $data[$k] * $f, 2);
            }
        }
        return $data;
    }
}
