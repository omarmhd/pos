<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\ExpenseInvoice;
use App\Models\ExpensePayment;
use App\Models\Setting;
use App\Models\Supplier;
use App\Services\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class ExpenseInvoiceController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:expenses.view')->only(['index', 'show']);
        $this->middleware('can:expenses.create')->only(['create', 'store']);
        $this->middleware('can:expenses.pay')->only(['payForm', 'pay']);
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $branchId = $this->effectiveBranchId($request);

            $query = ExpenseInvoice::with('expenseAccount', 'user')
                ->select('expense_invoices.*')
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId));

            return DataTables::eloquent($query)
                ->addColumn('account_name',  fn($e) => e($e->expenseAccount?->name ?? '—'))
                ->addColumn('user_name',     fn($e) => e($e->user?->name ?? '—'))
                ->addColumn('total_fmt',     fn($e) => number_format($e->total_amount, 2))
                ->addColumn('paid_fmt',      fn($e) => number_format($e->paid_amount, 2))
                ->addColumn('remaining_fmt', fn($e) => number_format($e->remainingAmount(), 2))
                ->addColumn('status_badge',  fn($e) => $this->statusBadge($e))
                ->addColumn('date',          fn($e) => $e->invoice_date->format('Y-m-d'))
                ->addColumn('due',           fn($e) => $e->due_date?->format('Y-m-d') ?? '—')
                ->addColumn('action',        fn($e) => $this->actionButtons($e))
                ->rawColumns(['status_badge', 'action'])
                ->make(true);
        }

        $branches     = \App\Models\Branch::where('is_active', true)->orderBy('name')->get(['id','name','code']);
        $branchId     = $this->effectiveBranchId($request);
        $branchLocked = $this->isBranchLocked();

        return view('expenses.index', compact('branches', 'branchId', 'branchLocked'));
    }

    public function create()
    {
        $currency = Setting::get('currency_symbol', 'ج.م');

        // Only leaf (non-header) expense accounts
        $expenseAccounts = Account::where('is_active', true)
            ->where('is_header', false)
            ->where('type', 'expense')
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $suppliers = Supplier::orderBy('name')->get(['id', 'name', 'company']);

        return view('expenses.create', compact('currency', 'expenseAccounts', 'suppliers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'invoice_date'         => 'required|date',
            'due_date'             => 'nullable|date|after_or_equal:invoice_date',
            'vendor_name'          => 'required|string|max:255',
            'supplier_id'          => 'nullable|exists:suppliers,id',
            'expense_account_id'   => 'required|exists:accounts,id',
            'vendor_invoice_number'=> 'nullable|string|max:100',
            'total_amount'         => 'required|numeric|min:0.01',
            'notes'                => 'nullable|string',
        ]);

        // Ensure the selected account is an expense leaf account
        $account = Account::findOrFail($request->expense_account_id);
        if ($account->is_header || $account->type !== 'expense') {
            return back()->withInput()
                ->with('error', 'يجب اختيار حساب مصروف تفصيلي (ليس حساباً إجمالياً)');
        }

        DB::beginTransaction();
        try {
            $invoice = ExpenseInvoice::create([
                'vendor_name'           => $request->vendor_name,
                'supplier_id'           => $request->supplier_id ?: null,
                'expense_account_id'    => $request->expense_account_id,
                'user_id'               => auth()->id(),
                'branch_id'             => auth()->user()->branch_id
                                           ?? Setting::get('default_branch_id'),
                'invoice_date'          => $request->invoice_date,
                'due_date'              => $request->due_date ?: null,
                'vendor_invoice_number' => $request->vendor_invoice_number ?: null,
                'total_amount'          => $request->total_amount,
                'notes'                 => $request->notes,
            ]);

            $entry = (new LedgerPostingService())->postExpenseInvoice($invoice->load('expenseAccount'));
            $invoice->update(['journal_entry_id' => $entry->id, 'is_posted' => true]);

            DB::commit();

            return redirect()->route('expense-invoices.show', $invoice)
                ->with('success', 'تم تسجيل فاتورة المصروف وترحيل القيد — ' . $invoice->invoice_number);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'خطأ: ' . $e->getMessage());
        }
    }

    public function show(ExpenseInvoice $expenseInvoice)
    {
        $expenseInvoice->load('expenseAccount', 'supplier', 'user', 'branch',
            'payments.user', 'journalEntry.lines.account');
        $currency = Setting::get('currency_symbol', 'ج.م');
        return view('expenses.show', compact('expenseInvoice', 'currency'));
    }

    /** Show the partial payment form */
    public function payForm(ExpenseInvoice $expenseInvoice)
    {
        if ($expenseInvoice->payment_status === 'paid') {
            return redirect()->route('expense-invoices.show', $expenseInvoice)
                ->with('error', 'الفاتورة مدفوعة بالكامل');
        }
        $currency = Setting::get('currency_symbol', 'ج.م');
        return view('expenses.pay', compact('expenseInvoice', 'currency'));
    }

    /** Process a payment against an expense invoice */
    public function pay(Request $request, ExpenseInvoice $expenseInvoice)
    {
        $request->validate([
            'payment_date'   => 'required|date',
            'payment_method' => 'required|in:cash,bank',
            'amount'         => 'required|numeric|min:0.01',
            'reference'      => 'nullable|string|max:255',
            'notes'          => 'nullable|string',
        ]);

        $remaining = $expenseInvoice->remainingAmount();
        if ($request->amount > $remaining + 0.005) {
            return back()->withInput()
                ->with('error', 'المبلغ المدفوع (' . number_format($request->amount, 2)
                    . ') أكبر من المتبقي (' . number_format($remaining, 2) . ')');
        }

        DB::beginTransaction();
        try {
            $payment = ExpensePayment::create([
                'expense_invoice_id' => $expenseInvoice->id,
                'user_id'            => auth()->id(),
                'branch_id'          => $expenseInvoice->branch_id,
                'payment_date'       => $request->payment_date,
                'payment_method'     => $request->payment_method,
                'amount'             => $request->amount,
                'reference'          => $request->reference,
                'notes'              => $request->notes,
            ]);

            // Post GL: DR AP / CR Cash-Bank
            $entry = (new LedgerPostingService())->postExpensePayment(
                $payment->load('expenseInvoice')
            );
            $payment->update(['journal_entry_id' => $entry->id]);

            // Update invoice paid_amount and status
            $newPaid  = round((float) $expenseInvoice->paid_amount + (float) $request->amount, 2);
            $total    = round((float) $expenseInvoice->total_amount, 2);
            $status   = match(true) {
                $newPaid >= $total - 0.005 => 'paid',
                $newPaid > 0              => 'partial',
                default                   => 'unpaid',
            };
            $expenseInvoice->update(['paid_amount' => $newPaid, 'payment_status' => $status]);

            DB::commit();

            return redirect()->route('expense-invoices.show', $expenseInvoice)
                ->with('success', 'تم تسجيل الدفعة وترحيل القيد بنجاح');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'خطأ: ' . $e->getMessage());
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function statusBadge(ExpenseInvoice $e): string
    {
        $overdue = $e->isOverdue() ? ' <span class="badge bg-danger ms-1">متأخرة</span>' : '';

        return match($e->payment_status) {
            'paid'    => "<span class='badge bg-success'>مدفوعة</span>",
            'partial' => "<span class='badge bg-warning text-dark'>جزئي</span>" . $overdue,
            default   => "<span class='badge bg-secondary'>غير مدفوعة</span>" . $overdue,
        };
    }

    private function actionButtons(ExpenseInvoice $e): string
    {
        $show = '<a href="' . route('expense-invoices.show', $e) . '"
                    class="btn btn-sm btn-info btn-action" title="عرض">
                    <i class="bi bi-eye"></i></a>';

        $pay = '';
        if ($e->payment_status !== 'paid' && auth()->user()?->can('expenses.pay')) {
            $pay = '<a href="' . route('expense-invoices.pay-form', $e) . '"
                       class="btn btn-sm btn-success btn-action" title="تسجيل دفعة">
                       <i class="bi bi-cash-coin"></i></a>';
        }

        return '<div class="d-flex gap-1 flex-nowrap">' . $show . $pay . '</div>';
    }
}
