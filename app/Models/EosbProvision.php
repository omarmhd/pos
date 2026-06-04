<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EosbProvision extends Model
{
    protected $fillable = [
        'employee_id', 'branch_id', 'journal_entry_id',
        'period_year', 'period_month',
        'base_salary', 'service_months', 'service_years',
        'provision_amount', 'cumulative_amount',
        'status', 'notes',
    ];

    protected $casts = [
        'base_salary'       => 'decimal:2',
        'service_months'    => 'decimal:2',
        'service_years'     => 'decimal:4',
        'provision_amount'  => 'decimal:2',
        'cumulative_amount' => 'decimal:2',
    ];

    public function employee()    { return $this->belongsTo(Employee::class); }
    public function branch()      { return $this->belongsTo(Branch::class); }
    public function journalEntry(){ return $this->belongsTo(JournalEntry::class); }

    public function periodLabel(): string
    {
        $months = [
            1=>'يناير',2=>'فبراير',3=>'مارس',4=>'أبريل',
            5=>'مايو',6=>'يونيو',7=>'يوليو',8=>'أغسطس',
            9=>'سبتمبر',10=>'أكتوبر',11=>'نوفمبر',12=>'ديسمبر',
        ];
        return ($months[$this->period_month] ?? $this->period_month) . ' ' . $this->period_year;
    }
}
