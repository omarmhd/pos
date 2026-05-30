<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySessionItem extends Model
{
    protected $fillable = [
        'session_id', 'product_id',
        'system_quantity', 'counted_quantity', 'difference',
        'cost_per_unit', 'notes',
    ];

    protected $casts = [
        'system_quantity'  => 'decimal:3',
        'counted_quantity' => 'decimal:3',
        'difference'       => 'decimal:3',
        'cost_per_unit'    => 'decimal:2',
    ];

    public function session(): BelongsTo  { return $this->belongsTo(InventorySession::class, 'session_id'); }
    public function product(): BelongsTo  { return $this->belongsTo(Product::class); }

    public function varianceValue(): float
    {
        return round((float)$this->difference * (float)$this->cost_per_unit, 2);
    }
}
