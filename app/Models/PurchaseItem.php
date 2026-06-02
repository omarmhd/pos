<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PurchaseItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_id', 'product_id',
        'quantity', 'unit_price', 'total_price',
    ];

    protected $casts = [
        'unit_price'  => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function purchase() { return $this->belongsTo(Purchase::class); }
    public function product()  { return $this->belongsTo(Product::class); }

    protected static function boot(): void
    {
        parent::boot();

        static::created(function (PurchaseItem $item) {
            $warehouseId = DB::table('purchases')
                ->where('id', $item->purchase_id)
                ->value('warehouse_id')
                ?? \App\Services\WarehouseService::getDefault()->id;

            // Last Purchase Price snapshot — replace with WAC in Phase 3
            Product::where('id', $item->product_id)
                ->update(['cost_price' => $item->unit_price]);

            // Append to event log. products.quantity recomputed by WarehouseService::in() in controller.
            StockMovement::create([
                'product_id'     => $item->product_id,
                'warehouse_id'   => $warehouseId,
                'reference_type' => static::class,
                'reference_id'   => $item->id,
                'quantity'       => $item->quantity,
                'cost'           => $item->unit_price,
                'movement_type'  => 'in',
                'notes'          => 'استلام بضاعة — فاتورة شراء',
            ]);
        });

        static::deleting(function (PurchaseItem $item) {
            $warehouseId = DB::table('purchases')
                ->where('id', $item->purchase_id)
                ->value('warehouse_id')
                ?? \App\Services\WarehouseService::getDefault()->id;

            $original = StockMovement::where('reference_type', static::class)
                ->where('reference_id', $item->id)
                ->where('is_reversal', false)
                ->first();

            if ($original) {
                StockMovement::create([
                    'product_id'     => $item->product_id,
                    'warehouse_id'   => $warehouseId,
                    'reference_type' => static::class,
                    'reference_id'   => $item->id,
                    'reversal_of'    => $original->id,
                    'is_reversal'    => true,
                    'quantity'       => $item->quantity,
                    'cost'           => $item->unit_price,
                    'movement_type'  => 'out',
                    'notes'          => 'عكس — حذف استلام',
                ]);
            }

            \App\Services\WarehouseService::out($warehouseId, $item->product_id, $item->quantity);
        });
    }
}
