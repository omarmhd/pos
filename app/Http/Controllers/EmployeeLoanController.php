<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\Setting;
use App\Services\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeLoanController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:hr.manage_loans');
    }

    public function index()
    {
        $loans    = EmployeeLoan::with('employee', 'user')
            ->orderByDesc('loan_date')
            ->get();
        $currency = Setting::get('currency_symbol', 'ج.م');

        return view('hr.loans.index', compact('loans', 'currency'));
    }

    public function create()
    {
        $employees = Employee::where('is_active', true)->orderBy('name')->get(['id', 'name', 'employee_code']);
        $currency  = Setting::get('currency_symbol', 'ج.م');

        return view('hr.loans.create', compact('employees', 'currency'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_id'          => 'required|exists:employees,id',
            'loan_date'            => 'required|date',
            'amount'               => 'required|numeric|min:1',
            'installments_total'   => 'required|integer|min:1|max:60',
            'payment_method'       => 'required|in:cash,bank',
            'notes'                => 'nullable|string',
        ]);

        $amount      = round((float) $request->amount, 2);
        $installments = (int) $request->installments_total;
        $installment  = round($amount / $installments, 2);

        DB::beginTransaction();
        try {
            // Check no other active loan exists for this employee (optional business rule)
            // Uncomment to enforce one-loan-at-a-time:
            // if (Employee::find($request->employee_id)->activeLoans()->exists()) {
            //     return back()->withInput()->with('error', 'لهذا الموظف سلفة نشطة — يجب تسديدها أولاً');
            // }

            $loan = EmployeeLoan::create([
                'employee_id'          => $request->employee_id,
                'loan_date'            => $request->loan_date,
                'amount'               => $amount,
                'remaining_balance'    => $amount,
                'monthly_installment'  => $installment,
                'installments_total'   => $installments,
                'installments_paid'    => 0,
                'status'               => 'active',
                'notes'                => $request->notes,
                'user_id'              => auth()->id(),
            ]);

            // GL: DR سلف الموظفين (1250) / CR صندوق/بنك (1000/1100)
            $loanCode = Setting::get('account_employee_loans_code', '1250');
            $cashCode = $request->payment_method === 'bank'
                ? Setting::get('account_bank_code', '1100')
                : Setting::get('account_cash_code', '1000');

            $loanAcct = Account::where('code', $loanCode)->firstOrFail();
            $cashAcct = Account::where('code', $cashCode)->firstOrFail();

            $employee = Employee::find($request->employee_id);

            $lines = [
                [
                    'account_id'       => $loanAcct->id,
                    'debit'            => $amount,
                    'credit'           => 0,
                    'line_description' => 'سلفة موظف – ' . $employee->name,
                ],
                [
                    'account_id'       => $cashAcct->id,
                    'debit'            => 0,
                    'credit'           => $amount,
                    'line_description' => 'صرف سلفة – ' . $employee->name,
                ],
            ];

            $entry = (new LedgerPostingService())->postEmployeeLoan($loan, $lines);
            $loan->update(['journal_entry_id' => $entry->id]);

            DB::commit();

            return redirect()->route('hr.loans.show', $loan)
                ->with('success', 'تم تسجيل السلفة وترحيل القيد — ' . number_format($amount, 2) . ' ' . Setting::get('currency_symbol', 'ج.م'));

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'خطأ: ' . $e->getMessage());
        }
    }

    public function show(EmployeeLoan $loan)
    {
        $loan->load('employee', 'user', 'journalEntry.lines.account');
        $currency = Setting::get('currency_symbol', 'ج.م');
        return view('hr.loans.show', compact('loan', 'currency'));
    }

    public function cancel(EmployeeLoan $loan)
    {
        if ($loan->status !== 'active') {
            return back()->with('error', 'السلفة ليست نشطة');
        }
        $loan->update(['status' => 'cancelled']);
        return redirect()->route('hr.loans.index')
            ->with('success', 'تم إلغاء السلفة');
    }
}
