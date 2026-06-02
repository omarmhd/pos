<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseInvoice extends Model
{
    protected $fillable = [
        'invoice_number', 'vendor_name', 'supplier_id',
        'expense_account_id', 'user_id', 'branch_id', 'journal_entry_id',
        'invoice_date', 'due_date', 'vendor_invoice_number',
        'total_amount', 'paid_amount', 'payment_status',
        'notes', 'is_posted',
    ];

    protected $casts = [
        'invoice_date'  => 'date',
        'due_date'      => 'date',
        'total_amount'  => 'decimal:2',
        'paid_amount'   => 'decimal:2',
        'is_posted'     => 'boolean',
    ];

    public function supplier()       { return $this->belongsTo(Supplier::class); }
    public function expenseAccount() { return $this->belongsTo(Account::class, 'expense_account_id'); }
    public function user()           { return $this->belongsTo(User::class); }
    public function branch()         { return $this->belongsTo(Branch::class); }
    public function journalEntry()   { return $this->belongsTo(JournalEntry::class); }
    public function payments()       { return $this->hasMany(ExpensePayment::class); }

    public function remainingAmount(): float
    {
        return round((float) $this->total_amount - (float) $this->paid_amount, 2);
    }

    public function isOverdue(): bool
    {
        return $this->due_date
            && $this->payment_status !== 'paid'
            && $this->due_date->isPast();
    }

    public function statusLabel(): string
    {
        return match($this->payment_status) {
            'paid'    => 'مدفوعة',
            'partial' => 'مدفوعة جزئياً',
            default   => 'غير مدفوعة',
        };
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($inv) {
            $inv->invoice_number = 'PENDING-' . uniqid('', true);
        });

        static::created(function ($inv) {
            $inv->updateQuietly([
                'invoice_number' => 'EXP-' . date('Ymd') . '-' . str_pad((string) $inv->id, 4, '0', STR_PAD_LEFT),
            ]);
        });
    }
}
