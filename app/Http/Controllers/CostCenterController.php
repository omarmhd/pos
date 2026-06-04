<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CostCenter;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CostCenterController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:accounts.view')->only(['index', 'report']);
        $this->middleware('can:accounts.create')->only(['create', 'store']);
        $this->middleware('can:accounts.edit')->only(['edit', 'update']);
    }

    public function index()
    {
        $centers = CostCenter::with('branch')->orderBy('code')->get();
        return view('accounting.cost-centers.index', compact('centers'));
    }

    public function create()
    {
        $branches = Branch::where('is_active', true)->orderBy('name')->get(['id','name','code']);
        return view('accounting.cost-centers.create', compact('branches'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'code'      => 'required|string|max:20|unique:cost_centers,code',
            'name'      => 'required|string|max:100',
            'branch_id' => 'nullable|exists:branches,id',
            'notes'     => 'nullable|string',
        ]);
        CostCenter::create($request->only('code','name','branch_id','notes') + ['is_active' => true]);
        return redirect()->route('cost-centers.index')->with('success', 'تم إنشاء مركز التكلفة');
    }

    public function edit(CostCenter $costCenter)
    {
        $branches = Branch::where('is_active', true)->orderBy('name')->get(['id','name','code']);
        return view('accounting.cost-centers.edit', compact('costCenter', 'branches'));
    }

    public function update(Request $request, CostCenter $costCenter)
    {
        $request->validate([
            'name'      => 'required|string|max:100',
            'branch_id' => 'nullable|exists:branches,id',
            'notes'     => 'nullable|string',
        ]);
        $costCenter->update($request->only('name','branch_id','notes','is_active'));
        return redirect()->route('cost-centers.index')->with('success', 'تم تحديث مركز التكلفة');
    }

    /**
     * P&L by cost center — shows revenue and expense lines attributed to each center.
     */
    public function report(Request $request)
    {
        $from     = Carbon::parse($request->input('from', now()->startOfYear()->toDateString()));
        $to       = Carbon::parse($request->input('to',   now()->toDateString()));
        $currency = Setting::get('currency_symbol', 'ج.م');

        $centers = CostCenter::where('is_active', true)->orderBy('code')->get();

        // For each center: sum revenue + expense lines
        $data = $centers->map(function (CostCenter $cc) use ($from, $to) {
            $lines = DB::table('journal_entry_lines as jel')
                ->join('journal_entries as je', 'jel.journal_entry_id', '=', 'je.id')
                ->join('accounts as a', 'jel.account_id', '=', 'a.id')
                ->where('jel.cost_center_id', $cc->id)
                ->whereBetween('je.entry_date', [$from->toDateString(), $to->toDateString()])
                ->select('a.type', 'a.code', 'a.name',
                         DB::raw('SUM(jel.debit) as total_debit'),
                         DB::raw('SUM(jel.credit) as total_credit'))
                ->groupBy('a.id', 'a.type', 'a.code', 'a.name')
                ->get();

            $revenue  = $lines->where('type', 'revenue')->sum(fn($l) => $l->total_credit - $l->total_debit);
            $expenses = $lines->where('type', 'expense')->sum(fn($l) => $l->total_debit  - $l->total_credit);

            return [
                'center'   => $cc,
                'lines'    => $lines,
                'revenue'  => round($revenue,  2),
                'expenses' => round($expenses, 2),
                'net'      => round($revenue - $expenses, 2),
            ];
        });

        return view('accounting.cost-centers.report', compact('data', 'from', 'to', 'currency'));
    }
}
