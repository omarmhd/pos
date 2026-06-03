<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesQuotationItem extends Model
{
    protected $fillable = [
        'quotation_id', 'product_id',
        'quantity', 'unit_price', 'discount_percent', 'total_price', 'notes',
    ];

    protected $casts = [
        'quantity'         => 'decimal:3',
        'unit_price'       => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'total_price'      => 'decimal:2',
    ];

    public function quotation() { return $this->belongsTo(SalesQuotation::class, 'quotation_id'); }
    public function product()   { return $this->belongsTo(Product::class); }

    public function discountedPrice(): float
    {
        return round((float) $this->unit_price * (1 - (float) $this->discount_percent / 100), 2);
    }
}
