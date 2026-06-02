<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockLevel extends Model
{
    protected $fillable = [
        'warehouse_id', 'product_id',
        'quantity', 'min_quantity',
    ];

    protected $casts = [
        'quantity'     => 'decimal:3',
        'min_quantity' => 'decimal:3',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function isLowStock(): bool
    {
        return (float) $this->quantity <= (float) $this->min_quantity;
    }
}
