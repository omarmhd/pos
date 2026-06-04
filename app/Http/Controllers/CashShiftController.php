<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CashShift;
use App\Models\Setting;
use App\Services\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class CashShiftController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:pos.shifts.view')->only(['index', 'show', 'data']);
        $this->middleware('can:pos.shifts.open')->only(['createOpen', 'storeOpen']);
        $this->middleware('can:pos.shifts.close')->only(['createClose', 'storeClose']);
    }

    // ── Index ────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $branchId = $this->effectiveBranchId($request);

            $query = CashShift::with('user:id,name', 'closedBy:id,name', 'branch:id,name')
                ->select('cash_shifts.*')
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId));

            return DataTables::eloquent($query)
                ->addColumn('opened_fmt',    fn($s) => $s->opened_at->format('Y-m-d H:i'))
                ->addColumn('closed_fmt',    fn($s) => $s->closed_at?->format('Y-m-d H:i') ?? '—')
                ->addColumn('cashier',       fn($s) => e($s->user?->name ?? '—'))
                ->addColumn('branch_name',   fn($s) => e($s->branch?->name ?? '—'))
                ->addColumn('status_badge',  fn($s) => $this->statusBadge($s))
                ->addColumn('opening_fmt',   fn($s) => number_format($s->opening_amount, 2))
                ->addColumn('expected_fmt',  fn($s) => $s->expected_amount !== null
                    ? number_format($s->expected_amount, 2) : '—')
                ->addColumn('closing_fmt',   fn($s) => $s->closing_amount !== null
                    ? number_format($s->closing_amount, 2) : '—')
                ->addColumn('variance_fmt',  fn($s) => $this->varianceBadge($s))
                ->addColumn('action',        fn($s) => $this->actionButtons($s))
                ->rawColumns(['status_badge', 'variance_fmt', 'action'])
                ->make(true);
        }

        $branches     = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        $branchId     = $this->effectiveBranchId($request);
        $branchLocked = $this->isBranchLocked();
        $currency     = Setting::get('currency_symbol', 'ج.م');

        // Current user's active shift
        $myShift = CashShift::activeForUser(auth()->id());

        return view('pos.shifts.index', compact(
            'branches', 'branchId', 'branchLocked', 'currency', 'myShift'
        ));
    }

    // ── Open Shift ───────────────────────────────────────────────────────────

    public function createOpen()
    {
        // Prevent opening a second shift while one is already open
        $existing = CashShift::activeForUser(auth()->id());
        if ($existing) {
            return redirect()->route('pos.shifts.show', $existing)
                ->with('warning', 'لديك وردية مفتوحة بالفعل — أغلقها أولاً قبل فتح وردية جديدة');
        }

        $currency = Setting::get('currency_symbol', 'ج.م');
        return view('pos.shifts.open', compact('currency'));
    }

    public function storeOpen(Request $request)
    {
        $request->validate([
            'opening_amount' => 'required|numeric|min:0',
            'notes'          => 'nullable|string|max:500',
        ]);

        $existing = CashShift::activeForUser(auth()->id());
        if ($existing) {
            return redirect()->route('pos.shifts.show', $existing)
                ->with('warning', 'لديك وردية مفتوحة بالفعل');
        }

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $shift = CashShift::create([
            'branch_id'       => $user->branch_id ?? Setting::get('default_branch_id'),
            'user_id'         => $user->id,
            'pos_terminal_id' => $user->pos_terminal_id ?? null,
            'status'          => 'open',
            'opened_at'       => now(),
            'opening_amount'  => $request->opening_amount,
            'notes'           => $request->notes,
        ]);

        return redirect()->route('pos.index')
            ->with('success', 'تم افتتاح الوردية بنجاح — الرصيد الافتتاحي: '
                . number_format($shift->opening_amount, 2)
                . ' ' . Setting::get('currency_symbol', 'ج.م'));
    }

    // ── Close Shift ──────────────────────────────────────────────────────────

    public function createClose(CashShift $shift)
    {
        abort_if($shift->status !== 'open', 403, 'الوردية غير مفتوحة');
        abort_if(
            $shift->user_id !== auth()->id() && !auth()->user()?->can('pos.shifts.manage'),
            403,
            'لا يمكنك إغلاق وردية مستخدم آخر'
        );

        // Recalculate expected amount from actual sales
        $shift->recalculate();

        $currency = Setting::get('currency_symbol', 'ج.م');
        return view('pos.shifts.close', compact('shift', 'currency'));
    }

    public function storeClose(Request $request, CashShift $shift)
    {
        abort_if($shift->status !== 'open', 403, 'الوردية غير مفتوحة');

        $request->validate([
            'closing_amount'  => 'required|numeric|min:0',
            'variance_reason' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            // Recalculate from actual sales data
            $shift->recalculate();

            $closing  = round((float) $request->closing_amount, 2);
            $expected = round((float) $shift->expected_amount, 2);
            $variance = round($closing - $expected, 2);

            $shift->closing_amount  = $closing;
            $shift->variance_amount = $variance;
            $shift->variance_reason = $request->variance_reason;
            $shift->status          = 'closed';
            $shift->closed_at       = now();
            $shift->closed_by       = auth()->id();
            $shift->save();

            // Post GL entry only when variance exists
            if (abs($variance) >= 0.01) {
                $entry = (new LedgerPostingService())->postShiftClose($shift);
                if ($entry) {
                    $shift->update(['journal_entry_id' => $entry->id]);
                }
            }

            DB::commit();

            return redirect()->route('pos.shifts.show', $shift)
                ->with('success', 'تم إقفال الوردية بنجاح'
                    . (abs($variance) >= 0.01
                        ? ' — فرق: ' . number_format(abs($variance), 2)
                          . ($variance > 0 ? ' (زيادة)' : ' (نقص)')
                        : ' — لا فروق نقدية'));

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'خطأ: ' . $e->getMessage());
        }
    }

    // ── Show ─────────────────────────────────────────────────────────────────

    public function show(CashShift $shift)
    {
        $shift->load('user', 'closedBy', 'branch', 'posTerminal', 'journalEntry.lines.account');

        // Sales summary for this shift
        $salesSummary = $shift->sales()
            ->selectRaw('payment_method,
                         COUNT(*) as count,
                         SUM(total_amount) as total')
            ->groupBy('payment_method')
            ->get();

        $currency = Setting::get('currency_symbol', 'ج.م');
        return view('pos.shifts.show', compact('shift', 'salesSummary', 'currency'));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function statusBadge(CashShift $s): string
    {
        return $s->status === 'open'
            ? "<span class='badge bg-success'>مفتوحة</span>"
            : "<span class='badge bg-secondary'>مغلقة</span>";
    }

    private function varianceBadge(CashShift $s): string
    {
        if (is_null($s->variance_amount)) return '<span class="text-muted">—</span>';
        $v = (float) $s->variance_amount;
        if (abs($v) < 0.01) return "<span class='badge bg-success'>متطابق</span>";
        $dir = $v > 0 ? 'زيادة' : 'نقص';
        $cls = $v > 0 ? 'info' : 'danger';
        return "<span class='badge bg-{$cls}'>{$dir} " . number_format(abs($v), 2) . "</span>";
    }

    private function actionButtons(CashShift $s): string
    {
        $show = '<a href="' . route('pos.shifts.show', $s) . '"
            class="btn btn-sm btn-info btn-action" title="عرض"><i class="bi bi-eye"></i></a>';

        $close = '';
        if ($s->status === 'open' && auth()->user()?->can('pos.shifts.close')) {
            $close = '<a href="' . route('pos.shifts.close', $s) . '"
                class="btn btn-sm btn-warning btn-action" title="إقفال">
                <i class="bi bi-lock-fill"></i></a>';
        }

        return '<div class="d-flex gap-1">' . $show . $close . '</div>';
    }
}
