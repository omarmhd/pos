<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseProductPrice extends Model
{
    protected $fillable = [
        'purchase_price_list_id', 'product_id', 'cost_price',
    ];

    protected $casts = ['cost_price' => 'decimal:2'];

    public function priceList()
    {
        return $this->belongsTo(PurchasePriceList::class, 'purchase_price_list_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
