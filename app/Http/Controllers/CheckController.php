<?php

namespace App\Http\Controllers;

use App\Models\Check;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\Supplier;
use App\Services\CheckPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class CheckController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:checks.view')->only(['index', 'data', 'show']);
        $this->middleware('can:checks.create')->only(['create', 'store']);
        $this->middleware('can:checks.transition')->only(['transition', 'endorse']);
        $this->middleware('can:checks.delete')->only(['destroy']);
    }

    public function index()
    {
        $currency = Setting::get('currency_symbol', 'ج.م');
        return view('checks.index', compact('currency'));
    }

    public function data(Request $request)
    {
        $type   = $request->input('type');
        $status = $request->input('status');
        $from   = $request->input('from');
        $to     = $request->input('to');

        $query = Check::with('customer', 'supplier', 'user', 'branch')
            ->when($type,   fn($q) => $q->where('type', $type))
            ->when($status, fn($q) => $q->where('status', $status))
            ->when($from,   fn($q) => $q->whereDate('due_date', '>=', $from))
            ->when($to,     fn($q) => $q->whereDate('due_date', '<=', $to))
            ->select('checks.*');

        return DataTables::eloquent($query)
            ->addColumn('type_badge', fn($c) => $c->typeBadge())
            ->addColumn('status_badge', fn($c) => $c->statusBadge())
            ->addColumn('party', fn($c) => e($c->partyName()))
            ->addColumn('check_date_fmt', fn($c) => $c->check_date->format('Y-m-d'))
            ->addColumn('due_date_fmt', fn($c) => $c->due_date->format('Y-m-d'))
            ->addColumn('amount_fmt', fn($c) => number_format($c->amount, 2))
            ->addColumn('action', fn($c) => $this->actionButtons($c))
            ->rawColumns(['type_badge', 'status_badge', 'action'])
            ->make(true);
    }

    public function create()
    {
        $customers   = Customer::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        // جدول الموردين لا يحتوي عمود is_active — كان يسبب SQLSTATE 42S22 في صفحة شيك جديد
        $suppliers   = Supplier::orderBy('name')->get(['id', 'name']);
        $currency    = Setting::get('currency_symbol', 'ج.م');
        $branches    = \App\Models\Branch::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $branchLocked = $this->isBranchLocked();
        $currencies  = \App\Models\Currency::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'is_base']);

        return view('checks.create', compact('customers', 'suppliers', 'currency', 'branches', 'branchLocked', 'currencies'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'type'           => 'required|in:receivable,payable',
            'check_ref'      => 'nullable|string|max:100',
            'check_date'     => 'required|date',
            'due_date'       => 'required|date|after_or_equal:check_date',
            'amount'         => 'required|numeric|min:0.01',
            'currency_id'    => 'nullable|exists:currencies,id',
            'exchange_rate'  => 'nullable|numeric|min:0',
            'foreign_amount' => 'nullable|numeric|min:0',
            'bank_name'      => 'nullable|string|max:200',
            'bank_branch'    => 'nullable|string|max:200',
            'account_number' => 'nullable|string|max:100',
            'customer_id'    => 'nullable|exists:customers,id',
            'supplier_id'    => 'nullable|exists:suppliers,id',
            'party_name'     => 'nullable|string|max:255',
            'notes'          => 'nullable|string',
            'branch_id'      => 'nullable|exists:branches,id',
        ]);

        // التحقق من وجود الطرف الآخر
        if ($request->type === 'receivable' && !$request->customer_id && !$request->party_name) {
            return back()->withInput()->withErrors(['customer_id' => 'يجب تحديد العميل أو كتابة اسم الطرف']);
        }
        if ($request->type === 'payable' && !$request->supplier_id && !$request->party_name) {
            return back()->withInput()->withErrors(['supplier_id' => 'يجب تحديد المورد أو كتابة اسم الطرف']);
        }

        DB::beginTransaction();
        try {
            $branchId = $this->isBranchLocked()
                ? (auth()->user()?->branch_id ?? Setting::get('default_branch_id'))
                : ($request->branch_id ?: auth()->user()?->branch_id ?: Setting::get('default_branch_id'));

            // العملة الأجنبية: المبلغ الأساسي = المبلغ الأجنبي × سعر الصرف (إن أُدخل)
            $rate    = (float) ($request->exchange_rate ?: 1) ?: 1;
            $foreign = $request->filled('foreign_amount') ? round((float) $request->foreign_amount, 2) : null;
            $baseAmount = $foreign !== null && $rate > 0
                ? round($foreign * $rate, 2)
                : round((float) $request->amount, 2);

            $check = Check::create([
                'type'           => $request->type,
                'check_ref'      => $request->check_ref,
                'check_date'     => $request->check_date,
                'due_date'       => $request->due_date,
                'amount'         => $baseAmount,
                'currency_id'    => $request->currency_id ?: null,
                'exchange_rate'  => $rate,
                'foreign_amount' => $foreign,
                'bank_name'      => $request->bank_name,
                'bank_branch'    => $request->bank_branch,
                'account_number' => $request->account_number,
                'customer_id'    => $request->customer_id ?: null,
                'supplier_id'    => $request->supplier_id ?: null,
                'party_name'     => $request->party_name,
                'notes'          => $request->notes,
                'branch_id'      => $branchId,
                'user_id'        => auth()->id(),
                // الحالة الابتدائية تُعيَّن في postReceived/postPending
                'status'         => $request->type === 'receivable' ? 'received' : 'pending',
            ]);

            $svc = new CheckPostingService();
            if ($request->type === 'receivable') {
                $svc->postReceived($check);
            } else {
                $svc->postPending($check);
            }

            DB::commit();

            return redirect()->route('checks.show', $check)
                ->with('success', 'تم تسجيل الشيك وترحيل القيد بنجاح — ' . $check->check_number);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'خطأ: ' . $e->getMessage());
        }
    }

    public function show(Check $check)
    {
        $check->load(
            'customer', 'supplier', 'user', 'branch',
            'journalEntry.lines.account',
            'depositJournalEntry.lines.account',
            'clearingJournalEntry.lines.account'
        );
        $currency  = Setting::get('currency_symbol', 'ج.م');
        $storeName = Setting::get('store_name', 'الميّزان');
        // الموردون لقائمة التجيير (تظهر فقط لشيك وارد تحت التحصيل)
        $suppliers = ($check->type === 'receivable' && $check->status === 'received')
            ? Supplier::orderBy('name')->get(['id', 'name'])
            : collect();
        return view('checks.show', compact('check', 'currency', 'storeName', 'suppliers'));
    }

    /**
     * تجيير شيك وارد لمورد (Endorsement) — مسار "1" في الأصيل.
     */
    public function endorse(Request $request, Check $check)
    {
        $request->validate(['supplier_id' => 'required|exists:suppliers,id']);

        if ($check->type !== 'receivable' || $check->status !== 'received') {
            return back()->with('error', 'لا يمكن تجيير هذا الشيك — يجب أن يكون واردًا في حالة "تحت التحصيل".');
        }

        DB::beginTransaction();
        try {
            (new CheckPostingService())->postEndorsed($check, (int) $request->supplier_id);
            DB::commit();

            return redirect()->route('checks.show', $check)
                ->with('success', 'تم تجيير الشيك للمورد وترحيل القيد بنجاح.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'خطأ: ' . $e->getMessage());
        }
    }

    /**
     * تغيير حالة الشيك مع الترحيل المحاسبي
     */
    public function transition(Request $request, Check $check)
    {
        $request->validate([
            'to_status' => 'required|string',
        ]);

        $toStatus = $request->to_status;

        if (!in_array($toStatus, $check->allowedTransitions())) {
            return back()->with('error', "الانتقال إلى الحالة [{$toStatus}] غير مسموح من الحالة الحالية.");
        }

        DB::beginTransaction();
        try {
            $svc = new CheckPostingService();

            match (true) {
                $check->type === 'receivable' && $toStatus === 'deposited' => $svc->postDeposited($check),
                $check->type === 'receivable' && $toStatus === 'cleared'   => $check->updateQuietly(['status' => 'cleared']),
                $check->type === 'receivable' && $toStatus === 'bounced'   => $svc->postBounced($check),
                $check->type === 'receivable' && $toStatus === 'received'  => $svc->postRepresented($check),
                $check->type === 'payable'    && $toStatus === 'cleared'   => $svc->postPayableCleared($check),
                $check->type === 'payable'    && $toStatus === 'returned'  => $svc->postReturned($check),
                default => throw new \RuntimeException("انتقال غير معروف: {$toStatus}"),
            };

            DB::commit();

            $label = $check->fresh()->statusLabel();
            return redirect()->route('checks.show', $check)
                ->with('success', "تم تحديث حالة الشيك إلى: {$label}");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'خطأ: ' . $e->getMessage());
        }
    }

    public function destroy(Check $check)
    {
        if ($check->status !== 'received' && $check->status !== 'pending') {
            return back()->with('error', 'لا يمكن حذف شيك تجاوز مرحلة الاستلام/الإصدار. أنشئ قيد تصحيح يدوياً.');
        }
        // حذف القيد المحاسبي الأول إن وجد
        if ($check->journal_entry_id) {
            $check->journalEntry?->lines()->delete();
            $check->journalEntry?->delete();
        }
        $check->delete();
        return redirect()->route('checks.index')
            ->with('success', 'تم حذف الشيك بنجاح.');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function actionButtons(Check $check): string
    {
        $show = '<a href="' . route('checks.show', $check) . '" class="btn btn-sm btn-info btn-action" title="عرض"><i class="bi bi-eye"></i></a>';
        $del  = '';

        /** @var \App\Models\User $user */
        $user = auth()->user();
        if ($user->can('checks.delete') && in_array($check->status, ['received', 'pending'])) {
            $token = csrf_token();
            $del = '<form action="' . route('checks.destroy', $check) . '" method="POST" class="d-inline"'
                . ' onsubmit="return confirm(\'هل تريد حذف هذا الشيك؟\')">'
                . '<input type="hidden" name="_token" value="' . $token . '">'
                . '<input type="hidden" name="_method" value="DELETE">'
                . '<button class="btn btn-sm btn-outline-secondary btn-action" title="حذف"><i class="bi bi-trash"></i></button></form>';
        }

        return '<div class="d-flex gap-1 flex-nowrap">' . $show . $del . '</div>';
    }
}
