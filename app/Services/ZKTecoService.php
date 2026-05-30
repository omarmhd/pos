<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Setting;
use Carbon\Carbon;
use Rats\Zkteco\Lib\ZKTeco;

class ZKTecoService
{
    private $zkteco;
    private $isConnected = false;

    public function __construct()
    {
        try {
            $this->zkteco = new ZKTeco(
                Setting::get('zkteco_ip', '192.168.1.100'),
                (int) Setting::get('zkteco_port', 4370)
            );
        } catch (\Exception $e) {
            \Log::error('ZKTeco Connection Error: ' . $e->getMessage());
        }
    }

    /**
     * اختبار الاتصال بالجهاز
     */
    public function testConnection()
    {
        try {
            if ($this->zkteco->connect()) {
                $info = $this->zkteco->getDeviceInfo();
                $this->zkteco->disconnect();
                return [
                    'success' => true,
                    'message' => 'الاتصال نجح ✅',
                    'info' => $info,
                ];
            }
            return ['success' => false, 'message' => 'فشل الاتصال بالجهاز'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'خطأ: ' . $e->getMessage()];
        }
    }

    /**
     * قراءة بيانات الحضور من الجهاز
     */
    public function getAttendance()
    {
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

    /**
     * قراءة قائمة الموظفين من الجهاز
     */
    public function getUsers()
    {
        try {
            if (!$this->zkteco->connect()) {
                return ['error' => 'فشل الاتصال'];
            }

            $users = $this->zkteco->getUser();
            $this->zkteco->disconnect();

            return $users ?? [];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * قراءة البصمات المسجلة للموظف
     */
    public function getFingerprints()
    {
        try {
            if (!$this->zkteco->connect()) {
                return ['error' => 'فشل الاتصال'];
            }

            $fingerprints = $this->zkteco->getFingerprint();
            $this->zkteco->disconnect();

            return $fingerprints ?? [];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * إضافة موظف جديد للجهاز
     */
    public function addUser($userId, $name)
    {
        try {
            if (!$this->zkteco->connect()) {
                return ['success' => false, 'message' => 'فشل الاتصال'];
            }

            $result = $this->zkteco->setUser($userId, $name);
            $this->zkteco->disconnect();

            return ['success' => true, 'message' => 'تم إضافة الموظف بنجاح'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * حذف موظف من الجهاز
     */
    public function deleteUser($userId)
    {
        try {
            if (!$this->zkteco->connect()) {
                return ['success' => false, 'message' => 'فشل الاتصال'];
            }

            $result = $this->zkteco->deleteUser($userId);
            $this->zkteco->disconnect();

            return ['success' => true, 'message' => 'تم حذف الموظف بنجاح'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * تصفير بيانات الجهاز (حذف جميع السجلات)
     */
    public function clearAttendance()
    {
        try {
            if (!$this->zkteco->connect()) {
                return ['success' => false, 'message' => 'فشل الاتصال'];
            }

            $this->zkteco->clearAttendance();
            $this->zkteco->disconnect();

            return ['success' => true, 'message' => 'تم تصفير البيانات بنجاح'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * مزامنة الوقت بين الجهاز والخادم
     */
    public function syncTime()
    {
        try {
            if (!$this->zkteco->connect()) {
                return ['success' => false, 'message' => 'فشل الاتصال'];
            }

            $this->zkteco->setTime(time());
            $this->zkteco->disconnect();

            return ['success' => true, 'message' => 'تم مزامنة الوقت بنجاح'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * الحصول على معلومات الجهاز
     */
    public function getDeviceInfo()
    {
        try {
            if (!$this->zkteco->connect()) {
                return ['error' => 'فشل الاتصال'];
            }

            $info = $this->zkteco->getVersion();
            $this->zkteco->disconnect();

            return $info ?? [];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * الحصول على حالة الجهاز (عدد الموظفين، السجلات، إلخ)
     */
    public function getDeviceStatus()
    {
        try {
            if (!$this->zkteco->connect()) {
                return ['error' => 'فشل الاتصال'];
            }

            $status = [
                'users_count' => count($this->zkteco->getUser()),
                'attendance_count' => count($this->zkteco->getAttendance()),
                'fingerprints_count' => count($this->zkteco->getFingerprint()),
            ];

            $this->zkteco->disconnect();

            return $status;
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * مزامنة البيانات وحفظها في قاعدة البيانات
     */
    public function syncToDatabase()
    {
        $attendance = $this->getAttendance();

        if (isset($attendance['error'])) {
            return ['synced' => 0, 'failed' => 0, 'error' => $attendance['error']];
        }

        $synced = 0;
        $failed = 0;

        foreach ($attendance as $record) {
            try {
                $employee = Employee::where('employee_code', $record['employee_id'])
                    ->orWhere('id', $record['employee_id'])
                    ->first();

                if (!$employee) continue;

                $checkTime = Carbon::parse($record['check_time']);
                $date = $checkTime->toDateString();

                $att = Attendance::firstOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'work_date' => $date,
                    ],
                    [
                        'status' => 'present',
                        'recorded_by' => 1,
                    ]
                );

                if ($record['status'] == 0) {
                    $att->check_in = $checkTime->toTimeString();
                } else {
                    $att->check_out = $checkTime->toTimeString();
                }

                // حساب ساعات العمل
                if ($att->check_in && $att->check_out) {
                    $in = Carbon::parse($att->check_in);
                    $out = Carbon::parse($att->check_out);
                    if ($out->gt($in)) {
                        $hours = round($in->diffInMinutes($out) / 60, 2);
                        $att->hours_worked = $hours;
                    }
                }

                $att->save();
                $synced++;
            } catch (\Exception $e) {
                $failed++;
                \Log::error('Attendance Sync Error: ' . $e->getMessage());
            }
        }

        return ['synced' => $synced, 'failed' => $failed];
    }
}
