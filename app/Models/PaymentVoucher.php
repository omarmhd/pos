<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentVoucher extends Model
{
    protected $fillable = [
        'voucher_number', 'voucher_date', 'paid_to', 'supplier_id',
        'account_id', 'cash_account_id', 'amount', 'payment_method',
        'reference', 'notes', 'user_id', 'is_posted', 'journal_entry_id',
        'branch_id',
    ];

    protected $casts = [
        'voucher_date' => 'date',
        'amount'       => 'decimal:2',
        'is_posted'    => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(fn($v) => $v->voucher_number = 'PENDING-' . uniqid());

        static::created(function ($v) {
            $v->updateQuietly([
                'voucher_number' => 'PV-' . date('Ymd') . '-' . str_pad($v->id, 4, '0', STR_PAD_LEFT),
            ]);
        });
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function cashAccount()
    {
        return $this->belongsTo(Account::class, 'cash_account_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
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
