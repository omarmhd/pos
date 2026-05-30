<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Setting;
use Carbon\Carbon;
use Rats\Zkteco\Lib\ZKTeco;

class AttendanceService
{
    private $zkteco;
    private $demoMode;

    public function __construct()
    {
        $this->demoMode = Setting::get('zkteco_mode', 'demo') === 'demo';

        if (!$this->demoMode) {
            $this->zkteco = new ZKTeco([
                'ip'       => Setting::get('zkteco_ip', '192.168.1.100'),
                'port'     => Setting::get('zkteco_port', 23),
                'username' => Setting::get('zkteco_username', ''),
                'password' => Setting::get('zkteco_password', ''),
            ]);
        }
    }

    // قراءة البيانات من الجهاز أو Demo
    public function fetchAttendance()
    {
        if ($this->demoMode) {
            return $this->generateDemoData();
        }

        try {
            if (!$this->zkteco->connect()) {
                return ['error' => 'فشل الاتصال بالجهاز'];
            }

            $attendance = $this->zkteco->getAttendance();
            $this->zkteco->disconnect();

            return $attendance ?? [];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    // توليد بيانات وهمية للتجريب
    private function generateDemoData()
    {
        $employees = Employee::active()->get(['id', 'employee_code']);
        $data = [];

        foreach ($employees as $emp) {
            // حضور صباحي في آخر 7 أيام
            for ($i = 1; $i <= 7; $i++) {
                if (rand(0, 100) < 85) { // 85% احتمالية الحضور
                    $date = now()->subDays($i);
                    $data[] = [
                        'employee_id'  => $emp->employee_code,
                        'check_time'   => $date->clone()->setHour(rand(7, 9))->setMinute(rand(0, 59)),
                        'status'       => 0, // check-in
                    ];

                    // حضور مسائي
                    if (rand(0, 1)) {
                        $data[] = [
                            'employee_id'  => $emp->employee_code,
                            'check_time'   => $date->clone()->setHour(rand(16, 18))->setMinute(rand(0, 59)),
                            'status'       => 1, // check-out
                        ];
                    }
                }
            }
        }

        return $data;
    }

    // مزامنة البيانات من ZKTeco
    public function syncAttendance($attendanceData = null)
    {
        if ($attendanceData === null) {
            $attendanceData = $this->fetchAttendance();
        }

        if (isset($attendanceData['error'])) {
            return $attendanceData;
        }

        $synced = 0;
        $failed = 0;
        $messages = [];

        foreach ($attendanceData as $record) {
            try {
                // البحث عن الموظف حسب الكود
                $employee = Employee::where('employee_code', $record['employee_id'])
                    ->orWhere('id', $record['employee_id'])
                    ->first();

                if (!$employee) {
                    $failed++;
                    $messages[] = "⚠ الموظف {$record['employee_id']} غير موجود";
                    continue;
                }

                $checkTime = Carbon::parse($record['check_time']);
                $isCheckIn = ($record['status'] == 0);
                $date = $checkTime->toDateString();

                // البحث عن سجل اليوم
                $attendance = Attendance::where('employee_id', $employee->id)
                    ->where('work_date', $date)
                    ->first();

                if (!$attendance) {
                    $attendance = new Attendance();
                    $attendance->employee_id = $employee->id;
                    $attendance->work_date = $date;
                    $attendance->status = 'present';
                }

                if ($isCheckIn) {
                    $attendance->check_in = $checkTime->toTimeString();
                } else {
                    $attendance->check_out = $checkTime->toTimeString();
                }

                // حساب ساعات العمل
                if ($attendance->check_in && $attendance->check_out) {
                    $in = Carbon::parse($attendance->check_in);
                    $out = Carbon::parse($attendance->check_out);
                    if ($out->gt($in)) {
                        $hours = round($in->diffInMinutes($out) / 60, 2);
                        $attendance->hours_worked = $hours;
                    }
                }

                $attendance->recorded_by = auth()->id() ?? 1;
                $attendance->save();

                $synced++;
            } catch (\Exception $e) {
                $failed++;
                $messages[] = "❌ خطأ: {$e->getMessage()}";
            }
        }

        return [
            'synced' => $synced,
            'failed' => $failed,
            'messages' => $messages,
        ];
    }
}
