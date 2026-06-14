<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'name', 'phone', 'email', 'address',
        'tax_number',   // مشتغل مرخص / الرقم الضريبي
        'credit_limit', 'notes', 'is_active',
        'price_list_id',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'is_active'    => 'boolean',
    ];

    public function priceList()
    {
        return $this->belongsTo(PriceList::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function payments()
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function deposits()
    {
        return $this->hasMany(CustomerDeposit::class);
    }

    public function returns()
    {
        return $this->hasMany(SaleReturn::class);
    }

    /** Current available deposit balance (ما أودعه العميل ناقص ما استُخدم) */
    public function depositBalance(): float
    {
        $deposited = (float) $this->deposits()->where('type', 'deposit')->sum('amount');
        $refunded  = (float) $this->deposits()->where('type', 'refund')->sum('amount');
        $used      = (float) $this->sales()->sum('balance_used');
        return max(0.0, $deposited - $refunded - $used);
    }

    /** Total value of credit sales (ever billed on account) */
    public function totalCreditBilled(): float
    {
        // الرصيد الآجل الحقيقي هو إجمالي الفاتورة مخصوماً منه المبالغ المدفوعة (نقدي، شيكات، أو رصيد إيداع)
        return (float) $this->sales()
            ->where('is_credit', true)
            ->sum(\Illuminate\Support\Facades\DB::raw('total_amount - IFNULL(cash_amount, 0) - IFNULL(cheque_amount, 0) - IFNULL(balance_used, 0)'));
    }

    /** Total value of returns credited to account */
    public function totalCreditReturned(): float
    {
        return (float) $this->returns()->where('refund_method', 'credit_note')->sum('total_amount');
    }

    /** Total payments received from this customer */
    public function totalPaid(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    /** Current outstanding balance */
    public function outstandingBalance(): float
    {
        return max(0, $this->totalCreditBilled() - $this->totalCreditReturned() - $this->totalPaid());
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
