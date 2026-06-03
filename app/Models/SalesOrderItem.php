<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrderItem extends Model
{
    protected $fillable = [
        'sales_order_id', 'product_id',
        'quantity_ordered', 'quantity_delivered',
        'unit_price', 'total_price', 'notes',
    ];

    protected $casts = [
        'quantity_ordered'   => 'decimal:3',
        'quantity_delivered' => 'decimal:3',
        'unit_price'         => 'decimal:2',
        'total_price'        => 'decimal:2',
    ];

    public function salesOrder() { return $this->belongsTo(SalesOrder::class); }
    public function product()    { return $this->belongsTo(Product::class); }

    public function remainingQuantity(): float
    {
        return max(0, (float) $this->quantity_ordered - (float) $this->quantity_delivered);
    }

    public function isFullyDelivered(): bool
    {
        return $this->remainingQuantity() < 0.001;
    }
}
