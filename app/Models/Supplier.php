<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'company',
        'tax_number',   // رقم التسجيل الضريبي (TRN)
        'purchase_price_list_id',
    ];

    public function purchasePriceList()
    {
        return $this->belongsTo(PurchasePriceList::class);
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function payments()
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function returns()
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    public function outstandingBalance(): float
    {
        $billed = (float) $this->purchases()
            ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as balance')
            ->value('balance');

        $returned = (float) $this->returns()
            ->where('refund_method', 'ap_deduction')
            ->sum('total_amount');

        return max(0, $billed - $returned);
    }
}
