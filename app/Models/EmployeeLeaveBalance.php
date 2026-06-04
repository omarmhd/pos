<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeLeaveBalance extends Model
{
    protected $fillable = [
        'employee_id', 'year', 'leave_type',
        'entitled_days', 'used_days', 'balance_days',
    ];

    protected $casts = [
        'used_days'    => 'decimal:1',
        'balance_days' => 'decimal:1',
    ];

    public function employee() { return $this->belongsTo(Employee::class); }

    /**
     * Get or create a leave balance record for an employee/year/type.
     * Annual default entitlement = 21 days (configurable per employee later).
     */
    public static function forEmployee(int $employeeId, int $year, string $type): static
    {
        return static::firstOrCreate(
            ['employee_id' => $employeeId, 'year' => $year, 'leave_type' => $type],
            ['entitled_days' => 21, 'used_days' => 0, 'balance_days' => 21]
        );
    }

    /** Deduct days from balance (called when leave is approved) */
    public function deduct(float $days): void
    {
        $this->increment('used_days',    $days);
        $this->decrement('balance_days', $days);
    }

    /** Restore days to balance (called when approved leave is cancelled/rejected) */
    public function restore(float $days): void
    {
        $this->decrement('used_days',    $days);
        $this->increment('balance_days', $days);
    }
}
