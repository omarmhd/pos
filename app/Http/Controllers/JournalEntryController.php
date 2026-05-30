<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Services\PdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class JournalEntryController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:journal_entries.view')->only(['index', 'show', 'pdf']);
        $this->middleware('can:journal_entries.create')->only(['create', 'store', 'destroy']);
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            // withSum avoids N+1 — single LEFT JOIN per aggregate
            $query = JournalEntry::query()
                ->with('user:id,name')
                ->withSum('lines as total_debit',  'debit')
                ->withSum('lines as total_credit', 'credit')
                ->select('journal_entries.*');

            return DataTables::eloquent($query)
                ->addColumn('entry_date_fmt', fn($e) =>
                    optional($e->entry_date)->format('Y-m-d') ?? '—')
                ->addColumn('source_badge', fn($e) => $this->sourceBadge($e))
                ->addColumn('debit_fmt', fn($e) =>
                    '<span class="font-monospace">' . number_format((float)$e->total_debit, 2) . '</span>')
                ->addColumn('balanced_icon', function ($e) {
                    $diff = abs((float)$e->total_debit - (float)$e->total_credit);
                    return $diff < 0.01
                        ? '<i class="bi bi-check-circle-fill text-success"></i>'
                        : '<i class="bi bi-exclamation-triangle-fill text-danger" title="فرق: ' . number_format($diff, 2) . '"></i>';
                })
                ->addColumn('user_name', fn($e) => e($e->user?->name ?? '—'))
                ->addColumn('action', fn($e) => $this->actionButtons($e))
                ->filterColumn('entry_date_fmt', fn($q, $k) =>
                    $q->whereDate('entry_date', 'like', "%{$k}%"))
                ->filterColumn('source_badge', fn($q, $k) =>
                    $q->where(function ($sq) use ($k) {
                        $sq->where('source_type', 'like', "%{$k}%")
                           ->orWhereNull('source_type');
                    }))
                ->filterColumn('user_name', fn($q, $k) =>
                    $q->whereHas('user', fn($u) => $u->where('name', 'like', "%{$k}%")))
                ->orderColumn('entry_date_fmt', 'entry_date $1')
                ->orderColumn('debit_fmt',      'total_debit $1')
                ->rawColumns(['source_badge', 'debit_fmt', 'balanced_icon', 'action'])
                ->make(true);
        }

        return view('journal_entries.index');
    }

    public function create()
    {
        $accounts = Account::where('is_active', true)
            ->where('is_header', false)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        return view('journal_entries.create', compact('accounts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'entry_date'               => 'required|date',
            'description'              => 'required|string|max:500',
            'reference'                => 'nullable|string|max:100',
            'lines'                    => 'required|array|min:2',
            'lines.*.account_id'       => 'required|exists:accounts,id',
            'lines.*.debit'            => 'required|numeric|min:0',
            'lines.*.credit'           => 'required|numeric|min:0',
            'lines.*.line_description' => 'nullable|string|max:255',
        ]);

        $totalDebit  = round(collect($request->lines)->sum('debit'),  2);
        $totalCredit = round(collect($request->lines)->sum('credit'), 2);

        if (abs($totalDebit - $totalCredit) > 0.005) {
            return back()->withInput()
                ->withErrors(['lines' => "القيد غير متوازن — مجموع المدين: {$totalDebit} / مجموع الدائن: {$totalCredit}"]);
        }

        if ($totalDebit <= 0) {
            return back()->withInput()
                ->withErrors(['lines' => 'يجب أن يحتوي القيد على مبالغ أكبر من صفر.']);
        }

        $accountIds  = collect($request->lines)->pluck('account_id')->unique();
        $headerCount = Account::whereIn('id', $accountIds)->where('is_header', true)->count();
        if ($headerCount > 0) {
            return back()->withInput()
                ->withErrors(['lines' => 'لا يمكن الترحيل على حساب رئيسي (إجمالي). اختر حساباً تفصيلياً.']);
        }

        DB::transaction(function () use ($request) {
            $entry = JournalEntry::create([
                'entry_date'  => $request->entry_date,
                'description' => $request->description,
                'reference'   => $request->reference,
                'source_type' => null,
                'source_id'   => null,
                'user_id'     => auth()->id(),
                'posted_at'   => now(),
            ]);

            foreach ($request->lines as $line) {
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $line['account_id'],
                    'debit'            => round((float) $line['debit'],  2),
                    'credit'           => round((float) $line['credit'], 2),
                    'line_description' => $line['line_description'] ?? null,
                ]);
            }
        });

        return redirect()->route('journal_entries.index')
            ->with('success', 'تم حفظ القيد اليومي بنجاح');
    }

    public function show($id)
    {
        $je = JournalEntry::with('user', 'lines.account')->findOrFail($id);
        return view('journal_entries.show', compact('je'));
    }

    public function pdf(JournalEntry $journalEntry): \Illuminate\Http\Response
    {
        $journalEntry->load('user', 'lines.account');
        $je = $journalEntry;
        return PdfService::arabic('pdf.journal_entry', compact('je'))
            ->download('journal-entry-' . $je->entry_number . '.pdf');
    }

    public function destroy($id)
    {
        $je = JournalEntry::findOrFail($id);

        if (!is_null($je->source_type)) {
            return back()->with('error', 'لا يمكن حذف قيد مولّد تلقائياً. استخدم قيد عكسي إذا لزم التصحيح.');
        }

        $je->lines()->delete();
        $je->delete();

        return redirect()->route('journal_entries.index')
            ->with('success', 'تم حذف القيد اليومي');
    }

    // ── private helpers ──────────────────────────────────────────────────────

    private function sourceBadge(JournalEntry $e): string
    {
        [$label, $cls] = match(true) {
            str_contains($e->source_type ?? '', 'Sale')               => ['مبيعات',        'bg-success'],
            str_contains($e->source_type ?? '', 'Purchase')           => ['مشتريات',       'bg-primary'],
            str_contains($e->source_type ?? '', 'SupplierPayment')    => ['دفعة مورد',     'bg-warning text-dark'],
            str_contains($e->source_type ?? '', 'CustomerPayment')    => ['تحصيل عميل',    'bg-info text-dark'],
            str_contains($e->source_type ?? '', 'PayrollRun')         => ['رواتب',          'bg-secondary'],
            str_contains($e->source_type ?? '', 'InventorySession')   => ['جرد دوري',       'bg-purple'],
            str_contains($e->source_type ?? '', 'InventoryAdjustment')=> ['تعديل مخزون',   'bg-orange'],
            is_null($e->source_type)                                  => ['يدوي',           'bg-dark'],
            default                                                   => ['قيد',            'bg-secondary'],
        };
        return "<span class='badge {$cls}'>{$label}</span>";
    }

    private function actionButtons(JournalEntry $e): string
    {
        $show = '<a href="' . route('journal_entries.show', $e->id) . '"
                    class="btn btn-outline-primary btn-sm btn-action" title="عرض">
                    <i class="bi bi-eye"></i></a>';

        $pdf = '<a href="' . route('journal_entries.pdf', $e->id) . '"
                   class="btn btn-outline-danger btn-sm btn-action" title="PDF" target="_blank">
                   <i class="bi bi-file-earmark-pdf"></i></a>';

        $delete = '';
        if (is_null($e->source_type) && auth()->user()?->can('journal_entries.create')) {
            $token = csrf_token();
            $url   = route('journal_entries.destroy', $e->id);
            $delete = <<<HTML
                <form action="{$url}" method="POST" class="d-inline"
                      onsubmit="return confirm('حذف هذا القيد نهائياً؟')">
                    <input type="hidden" name="_token" value="{$token}">
                    <input type="hidden" name="_method" value="DELETE">
                    <button class="btn btn-outline-secondary btn-sm btn-action" title="حذف">
                        <i class="bi bi-trash"></i>
                    </button>
                </form>
HTML;
        }

        return $show . $pdf . $delete;
    }
}
