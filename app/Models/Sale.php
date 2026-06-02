<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'user_id',
        'customer_id',
        'is_credit',
        'subtotal',
        'discount',
        'tax',
        'total_amount',
        'payment_method',
        'paid_amount',
        'balance_used',
        'change_amount',
        'is_posted',
        'is_reversed',
    ];

    protected $casts = [
        'subtotal'     => 'decimal:2',
        'discount'     => 'decimal:2',
        'tax'          => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount'  => 'decimal:2',
        'balance_used' => 'decimal:2',
        'change_amount'=> 'decimal:2',
        'is_credit'    => 'boolean',
        'is_posted'    => 'boolean',
        'is_reversed'  => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function customerPayments()
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function saleReturns()
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function outstandingBalance(): float
    {
        if (!$this->is_credit) {
            return 0.0;
        }

        $paid = $this->relationLoaded('customerPayments')
            ? (float) $this->customerPayments->sum('amount')
            : (float) $this->customerPayments()->sum('amount');

        // Credit notes reduce the outstanding balance for this invoice
        $creditNotes = $this->relationLoaded('saleReturns')
            ? (float) $this->saleReturns->where('refund_method', 'credit_note')->sum('total_amount')
            : (float) $this->saleReturns()->where('refund_method', 'credit_note')->sum('total_amount');

        return max(0, (float) $this->total_amount - $paid - $creditNotes);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($sale) {
            // Use MAX(id)+1 pattern via a temporary placeholder; replaced in created()
            $sale->invoice_number = 'PENDING-' . uniqid('', true);
        });

        static::created(function ($sale) {
            // Derive invoice number from the auto-increment id — always unique, no race condition
            $sale->updateQuietly([
                'invoice_number' => 'INV-' . date('Ymd') . '-' . str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT),
            ]);
        });
    }
}
