<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPrice extends Model
{
    protected $fillable = [
        'price_list_id', 'product_id',
        'selling_price', 'min_quantity',
    ];

    protected $casts = [
        'selling_price' => 'decimal:2',
        'min_quantity'  => 'decimal:3',
    ];

    public function priceList()
    {
        return $this->belongsTo(PriceList::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
