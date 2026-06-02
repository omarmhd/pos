<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollItem extends Model
{
    protected $fillable = [
        'payroll_run_id', 'employee_id',
        'base_salary', 'housing_allowance', 'transport_allowance',
        'other_allowances', 'overtime_pay',
        'absence_deduction', 'other_deductions', 'loan_deduction',
        'gross_pay', 'total_deductions', 'net_pay',
        'days_worked', 'days_absent', 'overtime_hours', 'notes',
    ];

    protected $casts = [
        'base_salary'        => 'decimal:2',
        'housing_allowance'  => 'decimal:2',
        'transport_allowance'=> 'decimal:2',
        'other_allowances'   => 'decimal:2',
        'overtime_pay'       => 'decimal:2',
        'absence_deduction'  => 'decimal:2',
        'other_deductions'   => 'decimal:2',
        'loan_deduction'     => 'decimal:2',
        'gross_pay'          => 'decimal:2',
        'total_deductions'   => 'decimal:2',
        'net_pay'            => 'decimal:2',
        'overtime_hours'     => 'decimal:2',
    ];

    public function payrollRun()
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
