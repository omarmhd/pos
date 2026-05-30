<?php

namespace App\Http\Controllers;

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
        $from = Carbon::parse($request->input('from', now()->startOfYear()->toDateString()));
        $to   = Carbon::parse($request->input('to',   now()->toDateString()));

        $data = $this->service->getIncomeStatement($from, $to);

        return view('accounting.income_statement', array_merge($data, compact('from', 'to')));
    }

    public function balanceSheet(Request $request)
    {
        $asOf    = Carbon::parse($request->input('as_of', now()->toDateString()));
        $ytdFrom = $asOf->copy()->startOfYear();

        // Net income is year-to-date up to the balance sheet date
        $incomeData = $this->service->getIncomeStatement($ytdFrom, $asOf);
        $netIncome  = $incomeData['netIncome'];

        $data = $this->service->getBalanceSheet($asOf, $netIncome);

        return view('accounting.balance_sheet', array_merge($data, compact('asOf', 'ytdFrom', 'netIncome')));
    }
}
