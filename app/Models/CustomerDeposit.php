<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerDeposit extends Model
{
    protected $fillable = [
        'voucher_number', 'voucher_date', 'customer_id', 'type',
        'amount', 'payment_method', 'reference', 'notes',
        'user_id', 'branch_id', 'is_posted', 'journal_entry_id',
    ];

    protected $casts = [
        'voucher_date' => 'date',
        'amount'       => 'decimal:2',
        'is_posted'    => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(fn($d) => $d->voucher_number = 'PENDING-' . uniqid());

        static::created(function ($d) {
            $prefix = $d->type === 'refund' ? 'RF' : 'CD';
            $d->updateQuietly([
                'voucher_number' => $prefix . '-' . date('Ymd') . '-' . str_pad($d->id, 4, '0', STR_PAD_LEFT),
            ]);
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return $this->type === 'deposit' ? 'إيداع' : 'استرداد';
    }

    public function paymentMethodLabel(): string
    {
        return match ($this->payment_method) {
            'cash'          => 'نقدي',
            'bank'          => 'تحويل بنكي',
            'mobile_wallet' => 'محفظة إلكترونية',
            default         => $this->payment_method,
        };
    }
}
