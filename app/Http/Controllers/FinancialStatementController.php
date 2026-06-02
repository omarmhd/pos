<?php

namespace App\Http\Controllers;

use App\Models\Branch;
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
        $from     = Carbon::parse($request->input('from', now()->startOfYear()->toDateString()));
        $to       = Carbon::parse($request->input('to',   now()->toDateString()));
        $branchId = $request->filled('branch_id') ? (int) $request->input('branch_id') : null;
        $branches = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        $branch   = $branchId ? Branch::find($branchId) : null;

        $data = $this->service->getIncomeStatement($from, $to, $branchId);

        return view('accounting.income_statement', array_merge(
            $data,
            compact('from', 'to', 'branches', 'branchId', 'branch')
        ));
    }

    public function balanceSheet(Request $request)
    {
        $asOf     = Carbon::parse($request->input('as_of', now()->toDateString()));
        $ytdFrom  = $asOf->copy()->startOfYear();
        $branchId = $request->filled('branch_id') ? (int) $request->input('branch_id') : null;
        $branches = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        $branch   = $branchId ? Branch::find($branchId) : null;

        $incomeData = $this->service->getIncomeStatement($ytdFrom, $asOf, $branchId);
        $netIncome  = $incomeData['netIncome'];

        $data = $this->service->getBalanceSheet($asOf, $netIncome, $branchId);

        return view('accounting.balance_sheet', array_merge(
            $data,
            compact('asOf', 'ytdFrom', 'netIncome', 'branches', 'branchId', 'branch')
        ));
    }
}
