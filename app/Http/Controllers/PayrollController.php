<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Attendance;
use App\Services\PdfService;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:hr.view_payroll')->only(['index', 'show', 'payslip']);
        $this->middleware('can:hr.create_payroll')->only(['preview', 'store']);
        $this->middleware('can:hr.approve_payroll')->only(['approve']);
    }

    public function index()
    {
        $runs = PayrollRun::with('createdBy')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate(12);

        return view('hr.payroll.index', compact('runs'));
    }

    /** Preview payroll for a month — calculate from attendance before saving */
    public function preview(Request $request)
    {
        $month = (int) $request->input('month', now()->month);
        $year  = (int) $request->input('year',  now()->year);

        // Prevent duplicate run
        $existing = PayrollRun::where('month', $month)->where('year', $year)->first();
        if ($existing) {
            return redirect()->route('hr.payroll.show', $existing)
                ->with('error', 'يوجد مسير راتب لهذه الفترة بالفعل');
        }

        $employees = Employee::where('is_active', true)->orderBy('name')->get();
        $items     = $this->calculateItems($employees, $month, $year);

        $totalGross      = collect($items)->sum('gross_pay');
        $totalDeductions = collect($items)->sum('total_deductions');
        $totalNet        = collect($items)->sum('net_pay');

        return view('hr.payroll.preview', compact('items', 'month', 'year',
            'totalGross', 'totalDeductions', 'totalNet'));
    }

    /** Save draft payroll run */
    public function store(Request $request)
    {
        $request->validate([
            'month'    => 'required|integer|between:1,12',
            'year'     => 'required|integer|min:2020',
            'pay_date' => 'required|date',
        ]);

        $month = (int) $request->month;
        $year  = (int) $request->year;

        if (PayrollRun::where('month', $month)->where('year', $year)->exists()) {
            return back()->with('error', 'يوجد مسير راتب لهذه الفترة بالفعل');
        }

        $employees = Employee::where('is_active', true)->get();
        $items     = $this->calculateItems($employees, $month, $year);

        DB::transaction(function () use ($items, $month, $year, $request) {
            $totalGross      = collect($items)->sum('gross_pay');
            $totalDeductions = collect($items)->sum('total_deductions');
            $totalNet        = collect($items)->sum('net_pay');

            $run = PayrollRun::create([
                'month'            => $month,
                'year'             => $year,
                'pay_date'         => $request->pay_date,
                'status'           => 'draft',
                'total_gross'      => $totalGross,
                'total_deductions' => $totalDeductions,
                'total_net'        => $totalNet,
                'notes'            => $request->notes,
                'created_by'       => auth()->id(),
            ]);

            foreach ($items as $item) {
                PayrollItem::create(array_merge($item, ['payroll_run_id' => $run->id]));
            }
        });

        $run = PayrollRun::where('month', $month)->where('year', $year)->firstOrFail();

        return redirect()->route('hr.payroll.show', $run)->with('success', 'تم إنشاء مسير الراتب كمسودة');
    }

    public function show(PayrollRun $payrollRun)
    {
        $payrollRun->load(['items.employee', 'createdBy', 'approvedBy', 'journalEntry']);
        return view('hr.payroll.show', compact('payrollRun'));
    }

    /**
     * Approve and post the payroll GL entry.
     *
     * Journal Entry (Salary expense posting):
     *   DR  Salaries Expense (6200)         → gross pay
     *   CR  Cash / Bank (1000/1100)          → net pay (what employees receive)
     *   CR  Salaries Payable (2100)          → any outstanding / deductions withheld
     */
    public function approve(PayrollRun $payrollRun)
    {
        if ($payrollRun->status !== 'draft') {
            return back()->with('error', 'يمكن الاعتماد فقط على المسودات');
        }

        DB::transaction(function () use ($payrollRun) {
            $salaryCode  = Setting::get('account_salaries_code',         '6200');
            $cashCode    = Setting::get('account_cash_code',             '1000');
            $payableCode = Setting::get('account_salaries_payable_code', '2100');

            $resolveAccount = function (string $code): Account {
                $account = Account::where('code', $code)
                    ->where('is_active', true)
                    ->where('is_header', false)
                    ->first();
                if (!$account) {
                    throw new \RuntimeException("حساب الرواتب غير موجود أو غير نشط: كود [{$code}]");
                }
                return $account;
            };

            $salaryAccount  = $resolveAccount($salaryCode);
            $cashAccount    = $resolveAccount($cashCode);
            $gross   = round((float) $payrollRun->total_gross, 2);
            $net     = round((float) $payrollRun->total_net,   2);
            $withheld = round($gross - $net, 2);

            $lines = [
                [
                    'account_id'       => $salaryAccount->id,
                    'debit'            => $gross,
                    'credit'           => 0,
                    'line_description' => 'إجمالي الرواتب – ' . $payrollRun->periodLabel(),
                ],
                [
                    'account_id'       => $cashAccount->id,
                    'debit'            => 0,
                    'credit'           => $net,
                    'line_description' => 'صرف رواتب صافي – ' . $payrollRun->periodLabel(),
                ],
            ];

            // CR Salaries Payable only when withheld > 0
            if ($withheld > 0) {
                $payableAccount = $resolveAccount($payableCode);
                $lines[] = [
                    'account_id'       => $payableAccount->id,
                    'debit'            => 0,
                    'credit'           => $withheld,
                    'line_description' => 'استقطاعات الرواتب – ' . $payrollRun->periodLabel(),
                ];
            }

            // Validate balance before persisting
            $debits  = round(array_sum(array_column($lines, 'debit')),  2);
            $credits = round(array_sum(array_column($lines, 'credit')), 2);
            if (abs($debits - $credits) > 0.005) {
                throw new \RuntimeException("قيد الرواتب غير متوازن — مدين: {$debits} / دائن: {$credits}");
            }

            $entry = JournalEntry::create([
                'entry_date'  => $payrollRun->pay_date,
                'reference'   => $payrollRun->reference,
                'source_type' => PayrollRun::class,
                'source_id'   => $payrollRun->id,
                'description' => 'مسير رواتب ' . $payrollRun->periodLabel(),
                'user_id'     => auth()->id(),
                'posted_at'   => now(),
            ]);

            foreach ($lines as $line) {
                JournalEntryLine::create(array_merge(['journal_entry_id' => $entry->id], $line));
            }

            $payrollRun->update([
                'status'           => 'approved',
                'approved_by'      => auth()->id(),
                'journal_entry_id' => $entry->id,
            ]);
        });

        return redirect()->route('hr.payroll.show', $payrollRun)
            ->with('success', 'تم اعتماد مسير الراتب وترحيل قيد الرواتب');
    }

    public function payslip(PayrollRun $payrollRun, PayrollItem $item)
    {
        if ($item->payroll_run_id !== $payrollRun->id) {
            abort(404);
        }
        $item->load('employee', 'payrollRun');

        return PdfService::arabic('pdf.payslip', compact('item'))
            ->download('payslip-' . $item->employee->employee_code . '-' . $payrollRun->reference . '.pdf');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function calculateItems($employees, int $month, int $year): array
    {
        // Load payroll settings once (not per employee)
        $workingDaysPerMonth  = (int) Setting::get('working_days_per_month',         26);
        $overtimeMultiplier   = (float) Setting::get('overtime_rate_multiplier',      1.5);
        $siEmployeePct        = (float) Setting::get('social_insurance_employee_pct', 9);

        $items = [];
        foreach ($employees as $emp) {
            $attendance = Attendance::where('employee_id', $emp->id)
                ->whereYear('work_date', $year)
                ->whereMonth('work_date', $month)
                ->get();

            $daysWorked    = $attendance->whereIn('status', ['present', 'half_day'])->count();
            $halfDays      = $attendance->where('status', 'half_day')->count();
            $daysAbsent    = $attendance->where('status', 'absent')->count();
            $overtimeHours = (float) $attendance->sum('overtime_hours');

            // Effective working days = present + 0.5 × half_day
            $effectiveDays = $daysWorked - ($halfDays * 0.5);

            if ($emp->pay_type === 'monthly') {
                // Prorate using configured working days (not raw calendar days)
                $basePay = $attendance->count() > 0
                    ? round((float) $emp->base_salary * $effectiveDays / $workingDaysPerMonth, 2)
                    : (float) $emp->base_salary;

                $housingPay   = (float) $emp->housing_allowance;
                $transportPay = (float) $emp->transport_allowance;
                $otherPay     = (float) $emp->other_allowances;
            } else {
                $basePay      = round($emp->dailyRate() * $effectiveDays, 2);
                $housingPay   = 0;
                $transportPay = 0;
                $otherPay     = 0;
            }

            // Overtime: configurable multiplier
            $overtimePay = round($emp->hourlyRate() * $overtimeMultiplier * $overtimeHours, 2);

            // Absence deduction
            $absenceDeduction = round($emp->dailyRate() * $daysAbsent, 2);

            $grossPay = $basePay + $housingPay + $transportPay + $otherPay + $overtimePay;

            // Social insurance employee contribution (on gross pay)
            $siDeduction = round($grossPay * $siEmployeePct / 100, 2);

            $totalDeductions = $absenceDeduction + $siDeduction;
            $netPay          = max(0, $grossPay - $totalDeductions);

            $items[] = [
                'employee_id'         => $emp->id,
                'employee'            => $emp,
                'base_salary'         => $basePay,
                'housing_allowance'   => $housingPay,
                'transport_allowance' => $transportPay,
                'other_allowances'    => $otherPay,
                'overtime_pay'        => $overtimePay,
                'absence_deduction'   => $absenceDeduction,
                'other_deductions'    => $siDeduction,   // social insurance stored here
                'gross_pay'           => round($grossPay, 2),
                'total_deductions'    => round($totalDeductions, 2),
                'net_pay'             => round($netPay, 2),
                'days_worked'         => (int) $daysWorked,
                'days_absent'         => (int) $daysAbsent,
                'overtime_hours'      => $overtimeHours,
            ];
        }

        return $items;
    }
}
