<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeLoan extends Model
{
    protected $fillable = [
        'employee_id', 'loan_date', 'amount', 'remaining_balance',
        'monthly_installment', 'installments_total', 'installments_paid',
        'status', 'notes', 'user_id', 'journal_entry_id',
    ];

    protected $casts = [
        'loan_date'           => 'date',
        'amount'              => 'decimal:2',
        'remaining_balance'   => 'decimal:2',
        'monthly_installment' => 'decimal:2',
        'status'              => 'string',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /** قسط الشهر الفعلي (لا يتجاوز الرصيد المتبقي) */
    public function currentInstallment(): float
    {
        if ($this->status !== 'active') return 0.0;
        return min((float) $this->monthly_installment, (float) $this->remaining_balance);
    }

    public function isSettled(): bool
    {
        return $this->remaining_balance <= 0.005;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'active'    => 'نشطة',
            'settled'   => 'مُسدَّدة',
            'cancelled' => 'ملغاة',
            default     => $this->status,
        };
    }
}
