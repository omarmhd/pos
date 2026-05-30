<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\Setting;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:hr.view_attendance')->only(['daily', 'monthly']);
        $this->middleware('can:hr.manage_attendance')->only(['saveDaily']);
    }

    /** Daily attendance register — show all active employees for a date */
    public function daily(Request $request)
    {
        $date      = Carbon::parse($request->input('date', today()->toDateString()));
        $employees = Employee::where('is_active', true)->orderBy('name')->get();
        $shifts    = Shift::where('is_active', true)->get();

        // Load existing records for this date
        $existing = Attendance::where('work_date', $date->toDateString())
            ->get()
            ->keyBy('employee_id');

        return view('hr.attendance.daily', compact('employees', 'shifts', 'existing', 'date'));
    }

    /** Save/update all attendance rows for a date at once */
    public function saveDaily(Request $request)
    {
        $request->validate([
            'date'       => 'required|date',
            'records'    => 'required|array',
        ]);

        $date = $request->input('date');

        foreach ($request->input('records', []) as $employeeId => $row) {
            $status = $row['status'] ?? 'present';

            $hoursWorked  = 0.0;
            $overtimeHrs  = 0.0;

            if (!empty($row['check_in']) && !empty($row['check_out'])) {
                $in  = Carbon::createFromTimeString($row['check_in']);
                $out = Carbon::createFromTimeString($row['check_out']);
                if ($out->gt($in)) {
                    $hoursWorked = round($in->diffInMinutes($out) / 60, 2);
                    $shiftHours  = (int) ($row['shift_hours'] ?? 8);
                    $overtimeHrs = max(0, $hoursWorked - $shiftHours);
                }
            }

            Attendance::updateOrCreate(
                ['employee_id' => $employeeId, 'work_date' => $date],
                [
                    'shift_id'       => $row['shift_id']  ?: null,
                    'check_in'       => $row['check_in']  ?: null,
                    'check_out'      => $row['check_out'] ?: null,
                    'hours_worked'   => $hoursWorked,
                    'overtime_hours' => $overtimeHrs,
                    'status'         => $status,
                    'notes'          => $row['notes'] ?? null,
                    'recorded_by'    => auth()->id(),
                ]
            );
        }

        return redirect()->back()->with('success', 'تم حفظ سجل الحضور بنجاح');
    }

    /** Monthly attendance summary for one employee */
    public function monthly(Request $request)
    {
        $employeeId = $request->input('employee_id');
        $month      = (int) $request->input('month', now()->month);
        $year       = (int) $request->input('year',  now()->year);
        $employees  = Employee::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        $records = [];
        $summary = ['present' => 0, 'absent' => 0, 'half_day' => 0, 'holiday' => 0, 'leave' => 0,
                    'total_hours' => 0, 'overtime_hours' => 0];

        if ($employeeId) {
            $employee = Employee::findOrFail($employeeId);
            $records  = Attendance::where('employee_id', $employeeId)
                ->whereYear('work_date', $year)
                ->whereMonth('work_date', $month)
                ->orderBy('work_date')
                ->get();

            foreach ($records as $r) {
                $summary[$r->status]    = ($summary[$r->status] ?? 0) + 1;
                $summary['total_hours'] += (float) $r->hours_worked;
                $summary['overtime_hours'] += (float) $r->overtime_hours;
            }
        }

        return view('hr.attendance.monthly', compact('employees', 'records', 'summary',
            'month', 'year', 'employeeId'));
    }

    // ── ZKTeco Sync Methods ────────────────────────────────────

    /** عرض صفحة مزامنة ZKTeco */
    public function syncIndex()
    {
        $this->authorize('hr.manage_attendance');
        $zkMode = Setting::get('zkteco_mode', 'demo');
        $zkEnabled = Setting::get('zkteco_enabled', '1');

        return view('hr.attendance.sync', compact('zkMode', 'zkEnabled'));
    }

    /** مزامنة يدوية من ZKTeco */
    public function syncNow(Request $request)
    {
        $this->authorize('hr.manage_attendance');

        $service = new AttendanceService();
        $result = $service->syncAttendance();

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        $message = "✅ تمت مزامنة {$result['synced']} سجل";
        if ($result['failed'] > 0) {
            $message .= " (فشل: {$result['failed']})";
        }

        return back()->with('success', $message);
    }

    /** معاينة البيانات من الجهاز */
    public function syncPreview()
    {
        $this->authorize('hr.manage_attendance');

        $service = new AttendanceService();
        $data = $service->fetchAttendance();

        if (isset($data['error'])) {
            return back()->with('error', 'خطأ: ' . $data['error']);
        }

        return view('hr.attendance.sync-preview', compact('data'));
    }
}
