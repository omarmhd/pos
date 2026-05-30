<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class LedgerController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:ledger.view');
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = DB::table('accounts as a')
                ->leftJoin('journal_entry_lines as jel', 'a.id', '=', 'jel.account_id')
                ->selectRaw('a.id, a.code, a.name, a.type,
                             COALESCE(SUM(jel.debit),  0) as total_debit,
                             COALESCE(SUM(jel.credit), 0) as total_credit')
                ->where('a.is_active', true)
                ->groupBy('a.id', 'a.code', 'a.name', 'a.type')
                ->havingRaw('COALESCE(SUM(jel.debit), 0) + COALESCE(SUM(jel.credit), 0) > 0');

            $typeMap = [
                'asset'     => ['أصل',        'primary'],
                'liability' => ['التزام',      'warning'],
                'equity'    => ['حقوق ملكية', 'info'],
                'revenue'   => ['إيراد',       'success'],
                'expense'   => ['مصروف',       'danger'],
            ];

            return DataTables::of($query)
                ->addColumn('type_badge', function ($row) use ($typeMap) {
                    [$label, $color] = $typeMap[$row->type] ?? [$row->type, 'secondary'];
                    return '<span class="badge bg-' . $color . '">' . $label . '</span>';
                })
                ->addColumn('net_balance', function ($row) {
                    $creditNormal = in_array($row->type, ['liability', 'equity', 'revenue']);
                    $net = $creditNormal
                        ? ((float)$row->total_credit - (float)$row->total_debit)
                        : ((float)$row->total_debit  - (float)$row->total_credit);
                    $cls = $net >= 0 ? 'text-success' : 'text-danger';
                    return '<span class="font-monospace ' . $cls . '">'
                        . number_format(abs($net), 2)
                        . ($net < 0 ? ' (م)' : '')
                        . '</span>';
                })
                ->addColumn('action', function ($row) {
                    return '<a href="' . route('accounting.ledger.show', $row->id) . '"
                               class="btn btn-sm btn-outline-primary">
                               <i class="bi bi-eye"></i> تفاصيل
                            </a>';
                })
                ->rawColumns(['type_badge', 'net_balance', 'action'])
                ->make(true);
        }

        return view('accounting.ledger_index');
    }

    public function show(Request $request, int $accountId)
    {
        $account = DB::table('accounts')->where('id', $accountId)->first();
        abort_if(!$account, 404);

        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'))->startOfDay()
            : null;
        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'))->endOfDay()
            : null;

        // Opening balance: cumulative up to the day before date_from
        $openingBalance = 0.0;
        if ($dateFrom) {
            $ob = DB::table('journal_entry_lines as jel')
                ->join('journal_entries as je', 'jel.journal_entry_id', '=', 'je.id')
                ->where('jel.account_id', $accountId)
                ->whereDate('je.entry_date', '<', $dateFrom->toDateString())
                ->selectRaw('SUM(jel.debit) as d, SUM(jel.credit) as c')
                ->first();
            $d = (float)($ob->d ?? 0);
            $c = (float)($ob->c ?? 0);
            $creditNormal = in_array($account->type, ['liability', 'equity', 'revenue']);
            $openingBalance = $creditNormal ? ($c - $d) : ($d - $c);
        }

        // Fetch period lines
        $linesQuery = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'jel.journal_entry_id', '=', 'je.id')
            ->where('jel.account_id', $accountId)
            ->orderBy('je.entry_date')
            ->orderBy('jel.id')
            ->select(
                'jel.id', 'jel.debit', 'jel.credit', 'jel.line_description',
                'je.id as journal_entry_id', 'je.entry_number', 'je.entry_date', 'je.description as je_description'
            );

        if ($dateFrom) {
            $linesQuery->whereDate('je.entry_date', '>=', $dateFrom->toDateString());
        }
        if ($dateTo) {
            $linesQuery->whereDate('je.entry_date', '<=', $dateTo->toDateString());
        }

        $allLines = $linesQuery->get();

        // Compute running balance
        $creditNormal = in_array($account->type, ['liability', 'equity', 'revenue']);
        $running = $openingBalance;
        $items = $allLines->map(function ($line) use ($creditNormal, &$running) {
            $running += $creditNormal
                ? ((float)$line->credit - (float)$line->debit)
                : ((float)$line->debit  - (float)$line->credit);
            return (object) array_merge((array) $line, ['running_balance' => $running]);
        });

        // Totals for the period
        $periodDebit  = $allLines->sum(fn($l) => (float)$l->debit);
        $periodCredit = $allLines->sum(fn($l) => (float)$l->credit);
        $closingBalance = $running;

        return view('accounting.ledger_show', compact(
            'account', 'items', 'openingBalance', 'closingBalance',
            'periodDebit', 'periodCredit', 'dateFrom', 'dateTo'
        ));
    }
}
