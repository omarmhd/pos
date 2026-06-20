<?php

namespace App\Http\Controllers;

use App\Models\Branch;
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
        $branchId    = $this->effectiveBranchId($request);
        $branchLocked = $this->isBranchLocked();

        if ($request->ajax()) {
            // INNER JOIN ensures only jel rows that have a matching je entry are counted.
            // Branch filter goes in WHERE (not JOIN condition) so it actually filters
            // the jel rows, not just the je join — LEFT JOIN + je condition was wrong
            // because jel.debit/credit from other branches were still summed.
            $query = DB::table('accounts as a')
                ->join('journal_entry_lines as jel', 'a.id', '=', 'jel.account_id')
                ->join('journal_entries as je', 'jel.journal_entry_id', '=', 'je.id')
                ->when($branchId, fn($q) => $q->where('je.branch_id', $branchId))
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
                ->addColumn('action', function ($row) use ($branchId) {
                    $url = route('accounting.ledger.show', $row->id)
                         . ($branchId ? '?branch_id=' . $branchId : '');
                    return '<a href="' . $url . '" class="btn btn-sm btn-outline-primary">
                               <i class="bi bi-eye"></i> تفاصيل
                            </a>';
                })
                ->rawColumns(['type_badge', 'net_balance', 'action'])
                ->make(true);
        }

        $branches = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);

        // أطراف الأستاذ المساعد (لمنتقي «كشف حساب طرف»)
        $customers = \App\Models\Customer::orderBy('name')->get(['id', 'name']);
        $suppliers = \App\Models\Supplier::orderBy('name')->get(['id', 'name']);
        $employees = \App\Models\Employee::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('accounting.ledger_index', compact(
            'branches', 'branchId', 'branchLocked', 'customers', 'suppliers', 'employees'
        ));
    }

    public function show(Request $request, int $accountId)
    {
        $account  = DB::table('accounts')->where('id', $accountId)->first();
        abort_if(!$account, 404);

        $branchId     = $this->effectiveBranchId($request);
        $branchLocked = $this->isBranchLocked();
        $branches = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        $branch   = $branchId ? Branch::find($branchId) : null;

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
                ->when($branchId, fn($q) => $q->where('je.branch_id', $branchId))
                ->selectRaw('SUM(jel.debit) as d, SUM(jel.credit) as c')
                ->first();
            $d = (float)($ob->d ?? 0);
            $c = (float)($ob->c ?? 0);
            $creditNormal   = in_array($account->type, ['liability', 'equity', 'revenue']);
            $openingBalance = $creditNormal ? ($c - $d) : ($d - $c);
        }

        // Fetch period lines
        $linesQuery = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'jel.journal_entry_id', '=', 'je.id')
            ->where('jel.account_id', $accountId)
            ->when($branchId, fn($q) => $q->where('je.branch_id', $branchId))
            ->orderBy('je.entry_date')
            ->orderBy('jel.id')
            ->select(
                'jel.id', 'jel.debit', 'jel.credit', 'jel.line_description',
                'je.id as journal_entry_id', 'je.entry_number', 'je.entry_date',
                'je.description as je_description', 'je.branch_id'
            );

        if ($dateFrom) $linesQuery->whereDate('je.entry_date', '>=', $dateFrom->toDateString());
        if ($dateTo)   $linesQuery->whereDate('je.entry_date', '<=', $dateTo->toDateString());

        $allLines = $linesQuery->get();

        $creditNormal = in_array($account->type, ['liability', 'equity', 'revenue']);
        $running      = $openingBalance;
        $items        = $allLines->map(function ($line) use ($creditNormal, &$running) {
            $running += $creditNormal
                ? ((float)$line->credit - (float)$line->debit)
                : ((float)$line->debit  - (float)$line->credit);
            return (object) array_merge((array) $line, ['running_balance' => $running]);
        });

        $periodDebit    = $allLines->sum(fn($l) => (float)$l->debit);
        $periodCredit   = $allLines->sum(fn($l) => (float)$l->credit);
        $closingBalance = $running;

        return view('accounting.ledger_show', compact(
            'account', 'items', 'openingBalance', 'closingBalance',
            'periodDebit', 'periodCredit', 'dateFrom', 'dateTo',
            'branches', 'branchId', 'branch', 'branchLocked'
        ));
    }

    /**
     * كشف حساب الأستاذ المساعد لطرف (دفتر الذمم).
     *
     * يُبنى من سطور القيود الموسومة بالطرف على حساب المراقبة (ذمم العملاء/الموردين)،
     * فيُظهر فقط ما يُنشئ أو يسوّي ذمة: المبيعات/المشتريات الآجلة + المدفوعات + المرتجعات.
     * المبيعات/المشتريات النقدية لا تظهر (لا تمرّ بالذمم) — وهذا هو الصحيح محاسبيًا،
     * ويضمن أن مجموع أرصدة كل الأطراف = رصيد حساب المراقبة في الأستاذ العام (مطابقة).
     *
     * الرصيد بنمط المدين الموجب: (مدين − دائن) تراكمي → موجب «مدين»، سالب «دائن».
     */
    public function party(Request $request, string $type, int $id)
    {
        $map = [
            'customer' => [\App\Models\Customer::class, 'عميل'],
            'supplier' => [\App\Models\Supplier::class, 'مورّد'],
            'employee' => [\App\Models\Employee::class, 'موظف'],
        ];
        abort_unless(isset($map[$type]), 404);
        [$class, $label] = $map[$type];
        $party = $class::findOrFail($id);

        $branchId     = $this->effectiveBranchId($request);
        $branchLocked = $this->isBranchLocked();
        $branches     = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        $branch       = $branchId ? Branch::find($branchId) : null;

        $dateFrom = $request->filled('date_from') ? Carbon::parse($request->input('date_from'))->startOfDay() : null;
        $dateTo   = $request->filled('date_to')   ? Carbon::parse($request->input('date_to'))->endOfDay()   : null;

        // وضع العرض: 'amounts' = كشف الذمم (افتراضي) | 'full' = كشف حساب كامل (نشاط)
        $mode = $request->input('mode') === 'full' ? 'full' : 'amounts';

        // قائمة حركات موحّدة: [date, ref, desc, url, debit, credit]
        $moves = collect();

        if ($mode === 'full') {
            // كشف كامل من المستندات (يُظهر كل الفواتير نقدًا وآجلًا + المدفوعات)
            if ($type === 'customer') {
                DB::table('sales')->where('customer_id', $id)
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->get(['id', 'invoice_number', 'total_amount', 'paid_amount', 'created_at'])
                    ->each(function ($s) use ($moves) {
                        $moves->push(['date' => $s->created_at, 'ref' => 'فاتورة بيع ' . $s->invoice_number, 'desc' => '',
                            'url' => route('sales.show', $s->id), 'debit' => (float) $s->total_amount, 'credit' => 0]);
                        if ((float) $s->paid_amount > 0) $moves->push(['date' => $s->created_at, 'ref' => 'مدفوع مع الفاتورة', 'desc' => $s->invoice_number,
                            'url' => null, 'debit' => 0, 'credit' => (float) $s->paid_amount]);
                    });
                DB::table('customer_payments')->where('customer_id', $id)->get(['id', 'amount', 'received_at'])
                    ->each(fn($p) => $moves->push(['date' => $p->received_at, 'ref' => 'تحصيل دفعة', 'desc' => '', 'url' => null, 'debit' => 0, 'credit' => (float) $p->amount]));
            } elseif ($type === 'supplier') {
                DB::table('purchases')->where('supplier_id', $id)
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->get(['id', 'invoice_number', 'total_amount', 'paid_amount', 'created_at'])
                    ->each(function ($pu) use ($moves) {
                        $moves->push(['date' => $pu->created_at, 'ref' => 'فاتورة شراء ' . $pu->invoice_number, 'desc' => '',
                            'url' => route('purchases.show', $pu->id), 'debit' => 0, 'credit' => (float) $pu->total_amount]);
                        if ((float) $pu->paid_amount > 0) $moves->push(['date' => $pu->created_at, 'ref' => 'مدفوع مع الفاتورة', 'desc' => $pu->invoice_number,
                            'url' => null, 'debit' => (float) $pu->paid_amount, 'credit' => 0]);
                    });
                DB::table('supplier_payments')->where('supplier_id', $id)->get(['id', 'amount', 'paid_at'])
                    ->each(fn($p) => $moves->push(['date' => $p->paid_at ?? now(), 'ref' => 'سداد دفعة', 'desc' => '', 'url' => null, 'debit' => (float) $p->amount, 'credit' => 0]));
            } else { // employee
                // الرواتب (مستحق + مصروف يتعادلان) + استقطاع السلف + السلف + مكافأة نهاية الخدمة
                DB::table('payroll_items as pi')
                    ->join('payroll_runs as pr', 'pr.id', '=', 'pi.payroll_run_id')
                    ->where('pi.employee_id', $id)
                    ->where('pr.status', '!=', 'draft')
                    ->when($branchId, fn($q) => $q->where('pr.branch_id', $branchId))
                    ->get(['pi.net_pay', 'pi.loan_deduction', 'pr.pay_date'])
                    ->each(function ($r) use ($moves) {
                        $net = (float) $r->net_pay;
                        $moves->push(['date' => $r->pay_date, 'ref' => 'راتب مستحق', 'desc' => '', 'url' => null, 'debit' => 0, 'credit' => $net]);
                        $moves->push(['date' => $r->pay_date, 'ref' => 'صرف الراتب', 'desc' => '', 'url' => null, 'debit' => $net, 'credit' => 0]);
                        if ((float) $r->loan_deduction > 0) $moves->push(['date' => $r->pay_date, 'ref' => 'سداد قسط سلفة', 'desc' => '', 'url' => null, 'debit' => 0, 'credit' => (float) $r->loan_deduction]);
                    });
                DB::table('employee_loans')->where('employee_id', $id)->get(['id', 'amount', 'loan_date'])
                    ->each(fn($l) => $moves->push(['date' => $l->loan_date, 'ref' => 'صرف سلفة', 'desc' => '', 'url' => null, 'debit' => (float) $l->amount, 'credit' => 0]));
                DB::table('eosb_provisions')->where('employee_id', $id)->where('status', 'posted')
                    ->get(['id', 'provision_amount', 'period_year', 'period_month', 'created_at'])
                    ->each(fn($e) => $moves->push(['date' => $e->created_at, 'ref' => 'مخصص نهاية خدمة', 'desc' => $e->period_month . '/' . $e->period_year, 'url' => null, 'debit' => 0, 'credit' => (float) $e->provision_amount]));
            }
        } else {
            // كشف الذمم: من سطور القيود الموسومة بالطرف (يطابق حساب المراقبة)
            DB::table('journal_entry_lines as jel')
                ->join('journal_entries as je', 'jel.journal_entry_id', '=', 'je.id')
                ->where('jel.party_type', $class)->where('jel.party_id', $id)
                ->when($branchId, fn($q) => $q->where('je.branch_id', $branchId))
                ->orderBy('je.entry_date')->orderBy('jel.id')
                ->select('jel.debit', 'jel.credit', 'jel.line_description as ld', 'je.entry_number',
                         'je.entry_date', 'je.id as jeid', 'je.description as jed')
                ->get()
                ->each(fn($l) => $moves->push(['date' => $l->entry_date, 'ref' => $l->entry_number ?: ('#' . $l->jeid),
                    'desc' => $l->ld ?: $l->jed, 'url' => route('journal_entries.show', $l->jeid),
                    'debit' => (float) $l->debit, 'credit' => (float) $l->credit]));
        }

        $moves = $moves->sortBy('date')->values();

        $openingBalance = 0.0; $running = 0.0; $items = collect();
        foreach ($moves as $m) {
            $signed = round($m['debit'] - $m['credit'], 2);
            $d = Carbon::parse($m['date']);
            if ($dateFrom && $d->lt($dateFrom)) { $openingBalance += $signed; $running += $signed; continue; }
            if ($dateTo && $d->gt($dateTo)) continue;
            $running = round($running + $signed, 2);
            $items->push((object) [
                'date' => $d->toDateString(), 'ref' => $m['ref'], 'desc' => $m['desc'] ?? '', 'url' => $m['url'],
                'debit' => round($m['debit'], 2), 'credit' => round($m['credit'], 2), 'running_balance' => $running,
            ]);
        }
        $openingBalance = round($openingBalance, 2);
        $periodDebit    = round($items->sum('debit'), 2);
        $periodCredit   = round($items->sum('credit'), 2);
        $closingBalance = round($running, 2);

        return view('accounting.party_ledger', compact(
            'party', 'label', 'type', 'mode', 'items', 'openingBalance', 'closingBalance',
            'periodDebit', 'periodCredit', 'dateFrom', 'dateTo',
            'branches', 'branchId', 'branch', 'branchLocked'
        ));
    }
}
