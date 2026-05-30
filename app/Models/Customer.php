<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'name', 'phone', 'email', 'address',
        'credit_limit', 'notes', 'is_active',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'is_active'    => 'boolean',
    ];

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function payments()
    {
        return $this->hasMany(CustomerPayment::class);
    }

    /** Total value of credit sales (ever billed on account) */
    public function totalCreditBilled(): float
    {
        return (float) $this->sales()->where('is_credit', true)->sum('total_amount');
    }

    /** Total payments received from this customer */
    public function totalPaid(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    /** Current outstanding balance */
    public function outstandingBalance(): float
    {
        return $this->totalCreditBilled() - $this->totalPaid();
    }

    /** Remaining credit available */
    public function availableCredit(): float
    {
        return max(0, (float) $this->credit_limit - $this->outstandingBalance());
    }

    /** Outstanding amount for a specific sale */
    public function saleOutstanding(int $saleId): float
    {
        $sale = $this->sales()->find($saleId);
        if (!$sale || !$sale->is_credit) {
            return 0.0;
        }
        $paid = $this->payments()->where('sale_id', $saleId)->sum('amount');
        return max(0, (float) $sale->total_amount - (float) $paid);
    }
}
