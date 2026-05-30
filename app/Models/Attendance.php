<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $table = 'attendance';

    protected $fillable = [
        'employee_id', 'shift_id', 'work_date',
        'check_in', 'check_out', 'hours_worked', 'overtime_hours',
        'status', 'notes', 'recorded_by',
    ];

    protected $casts = [
        'work_date'     => 'date',
        'hours_worked'  => 'decimal:2',
        'overtime_hours'=> 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
