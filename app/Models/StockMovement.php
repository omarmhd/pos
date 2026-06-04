<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Append-only event log for all stock movements.
 *
 * ARCHITECTURE RULE: StockMovement records are NEVER deleted.
 * To reverse a movement, create a new record with:
 *   - movement_type = opposite (in ↔ out)
 *   - reversal_of   = id of the original movement
 *   - is_reversal   = true
 *
 * Net balance for (product, warehouse) = SUM(all movements with sign)
 */
class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'warehouse_id',
        'reference_type', 'reference_id',
        'quantity', 'cost', 'movement_type',
        'lot_number', 'expiry_date',
        'notes',
        'reversal_of', 'is_reversal',
    ];

    protected $casts = [
        'quantity'    => 'decimal:4',
        'cost'        => 'decimal:2',
        'is_reversal' => 'boolean',
        'expiry_date' => 'date',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** The original movement this record reverses (null for normal movements). */
    public function originalMovement()
    {
        return $this->belongsTo(static::class, 'reversal_of');
    }

    /** All reversal records created for this movement. */
    public function reversals()
    {
        return $this->hasMany(static::class, 'reversal_of');
    }

    protected static function boot(): void
    {
        parent::boot();

        // ENFORCE append-only: prevent accidental deletion
        static::deleting(function (StockMovement $sm) {
            throw new RuntimeException(
                'StockMovement is append-only — deletion is forbidden. '
                . 'Create a reversal record instead (is_reversal=true).'
            );
        });
    }
}
