<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'employee_code', 'name', 'national_id', 'phone', 'email', 'address',
        'hire_date', 'termination_date', 'employment_type', 'pay_type',
        'base_salary', 'housing_allowance', 'transport_allowance', 'other_allowances',
        'bank_account', 'department', 'job_title', 'is_active', 'notes',
        'eosb_tiers', 'eosb_salary_base',
    ];

    protected $casts = [
        'hire_date'          => 'date',
        'termination_date'   => 'date',
        'base_salary'        => 'decimal:2',
        'housing_allowance'  => 'decimal:2',
        'transport_allowance'=> 'decimal:2',
        'other_allowances'   => 'decimal:2',
        'is_active'          => 'boolean',
        'eosb_tiers'         => 'array',
    ];

    public function attendance()
    {
        return $this->hasMany(Attendance::class);
    }

    public function payrollItems()
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function loans()
    {
        return $this->hasMany(EmployeeLoan::class);
    }

    public function activeLoans()
    {
        return $this->loans()->where('status', 'active')->where('remaining_balance', '>', 0);
    }

    /** مجموع الأقساط المستحقة هذا الشهر من جميع السلف النشطة */
    public function pendingLoanInstallment(): float
    {
        return $this->activeLoans()->get()->sum(fn($l) => $l->currentInstallment());
    }

    /** رصيد السلف الكلي المتبقي */
    public function totalLoanBalance(): float
    {
        return (float) $this->activeLoans()->sum('remaining_balance');
    }

    /** Gross monthly salary (all fixed allowances) */
    public function grossMonthlySalary(): float
    {
        return (float) $this->base_salary
            + (float) $this->housing_allowance
            + (float) $this->transport_allowance
            + (float) $this->other_allowances;
    }

    /** Daily rate derived from monthly salary (÷ 30) */
    public function dailyRate(): float
    {
        if ($this->pay_type === 'daily') {
            return (float) $this->base_salary;
        }
        return $this->grossMonthlySalary() / 30;
    }

    /** Hourly rate */
    public function hourlyRate(): float
    {
        if ($this->pay_type === 'hourly') {
            return (float) $this->base_salary;
        }
        return $this->dailyRate() / 8;
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($emp) {
            if (empty($emp->employee_code)) {
                $count = static::count() + 1;
                $emp->employee_code = 'EMP-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
            }
        });
    }
}
