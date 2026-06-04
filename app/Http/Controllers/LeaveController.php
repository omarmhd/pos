<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\EmployeeLeaveBalance;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:hr.leaves.view')->only(['index', 'show']);
        $this->middleware('can:hr.leaves.create')->only(['create', 'store']);
        $this->middleware('can:hr.leaves.manage')->only(['approve', 'reject']);
    }

    public function index(Request $request)
    {
        $status   = $request->input('status', '');
        $year     = (int) $request->input('year', now()->year);
        $currency = Setting::get('currency_symbol', 'ج.م');

        $leaves = EmployeeLeave::with('employee', 'approvedBy')
            ->when($status, fn($q) => $q->where('status', $status))
            ->whereYear('start_date', $year)
            ->orderByDesc('start_date')
            ->paginate(25)
            ->withQueryString();

        return view('hr.leaves.index', compact('leaves', 'status', 'year', 'currency'));
    }

    public function create()
    {
        $employees = Employee::where('is_active', true)->orderBy('name')->get(['id','name','employee_code']);
        $types     = EmployeeLeave::TYPES;
        return view('hr.leaves.create', compact('employees', 'types'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type'  => 'required|in:' . implode(',', array_keys(EmployeeLeave::TYPES)),
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'notes'       => 'nullable|string|max:500',
        ]);

        $start       = Carbon::parse($request->start_date);
        $end         = Carbon::parse($request->end_date);
        $workingDays = EmployeeLeave::calcWorkingDays($start, $end);

        EmployeeLeave::create([
            'employee_id'  => $request->employee_id,
            'leave_type'   => $request->leave_type,
            'start_date'   => $request->start_date,
            'end_date'     => $request->end_date,
            'working_days' => $workingDays,
            'status'       => 'pending',
            'notes'        => $request->notes,
        ]);

        return redirect()->route('hr.leaves.index')
            ->with('success', 'تم تقديم طلب الإجازة بنجاح — ' . $workingDays . ' أيام عمل');
    }

    public function show(EmployeeLeave $leave)
    {
        $leave->load('employee', 'approvedBy');
        $balance = EmployeeLeaveBalance::forEmployee(
            $leave->employee_id,
            $leave->start_date->year,
            $leave->leave_type
        );
        return view('hr.leaves.show', compact('leave', 'balance'));
    }

    public function approve(EmployeeLeave $leave)
    {
        abort_if($leave->status !== 'pending', 403, 'الإجازة ليست قيد الانتظار');

        $leave->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        // Deduct from leave balance (for annual + sick leaves)
        if ($leave->leave_type !== 'emergency') {
            $balance = EmployeeLeaveBalance::forEmployee(
                $leave->employee_id,
                $leave->start_date->year,
                $leave->leave_type
            );
            $balance->deduct($leave->working_days);
        }

        return back()->with('success', 'تم اعتماد الإجازة — '
            . $leave->working_days . ' أيام عمل');
    }

    public function reject(Request $request, EmployeeLeave $leave)
    {
        abort_if($leave->status !== 'pending', 403, 'الإجازة ليست قيد الانتظار');

        $request->validate(['rejection_reason' => 'required|string|max:500']);

        $leave->update([
            'status'           => 'rejected',
            'approved_by'      => auth()->id(),
            'approved_at'      => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        return back()->with('success', 'تم رفض طلب الإجازة');
    }
}
