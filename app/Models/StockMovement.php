<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'warehouse_id', 'reference_type', 'reference_id', 'quantity', 'cost', 'movement_type', 'notes'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
