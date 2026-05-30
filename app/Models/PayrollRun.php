<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollRun extends Model
{
    protected $fillable = [
        'reference', 'month', 'year', 'pay_date', 'status',
        'total_gross', 'total_deductions', 'total_net',
        'notes', 'created_by', 'approved_by', 'journal_entry_id',
    ];

    protected $casts = [
        'pay_date'          => 'date',
        'total_gross'       => 'decimal:2',
        'total_deductions'  => 'decimal:2',
        'total_net'         => 'decimal:2',
    ];

    public function items()
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function periodLabel(): string
    {
        $months = [
            1=>'يناير',2=>'فبراير',3=>'مارس',4=>'أبريل',
            5=>'مايو',6=>'يونيو',7=>'يوليو',8=>'أغسطس',
            9=>'سبتمبر',10=>'أكتوبر',11=>'نوفمبر',12=>'ديسمبر',
        ];
        return ($months[$this->month] ?? $this->month) . ' ' . $this->year;
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($run) {
            $seq = static::whereYear('created_at', now()->year)->count() + 1;
            $run->reference = 'PAY-' . now()->format('Ym') . '-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
        });
    }
}
