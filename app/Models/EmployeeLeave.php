<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class EmployeeLeave extends Model
{
    protected $fillable = [
        'employee_id', 'approved_by',
        'leave_type', 'start_date', 'end_date', 'working_days',
        'status', 'approved_at', 'rejection_reason', 'notes',
    ];

    protected $casts = [
        'start_date'  => 'date',
        'end_date'    => 'date',
        'approved_at' => 'datetime',
    ];

    public const TYPES = [
        'annual'    => 'إجازة سنوية',
        'sick'      => 'إجازة مرضية',
        'unpaid'    => 'إجازة بدون راتب',
        'maternity' => 'إجازة أمومة',
        'emergency' => 'إجازة طارئة',
    ];

    public const STATUSES = [
        'pending'  => ['label' => 'قيد الانتظار', 'color' => 'warning'],
        'approved' => ['label' => 'معتمدة',        'color' => 'success'],
        'rejected' => ['label' => 'مرفوضة',        'color' => 'danger'],
    ];

    public function employee()   { return $this->belongsTo(Employee::class); }
    public function approvedBy() { return $this->belongsTo(User::class, 'approved_by'); }

    public function typeLabel(): string  { return self::TYPES[$this->leave_type] ?? $this->leave_type; }
    public function statusLabel(): string { return self::STATUSES[$this->status]['label'] ?? $this->status; }
    public function statusColor(): string { return self::STATUSES[$this->status]['color'] ?? 'secondary'; }
    public function isPaid(): bool        { return $this->leave_type !== 'unpaid'; }

    /** Calculate working days between two dates (excludes Fridays — typical Gulf weekend) */
    public static function calcWorkingDays(Carbon $start, Carbon $end): int
    {
        $days = 0;
        $current = $start->copy();
        while ($current->lte($end)) {
            if (!$current->isFriday()) {
                $days++;
            }
            $current->addDay();
        }
        return max(1, $days);
    }
}
