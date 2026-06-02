<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id', 'product_id',
        'quantity', 'unit_price', 'cost_price', 'total_price',
    ];

    protected $casts = [
        'unit_price'  => 'decimal:2',
        'cost_price'  => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function sale()    { return $this->belongsTo(Sale::class); }
    public function product() { return $this->belongsTo(Product::class); }

    protected static function boot(): void
    {
        parent::boot();

        static::created(function (SaleItem $item) {
            $warehouseId = DB::table('sales')
                ->where('id', $item->sale_id)
                ->value('warehouse_id')
                ?? \App\Services\WarehouseService::getDefault()->id;

            // Append to event log — do NOT touch products.quantity here.
            // products.quantity is recomputed by WarehouseService::out() called from PosController.
            StockMovement::create([
                'product_id'     => $item->product_id,
                'warehouse_id'   => $warehouseId,
                'reference_type' => static::class,
                'reference_id'   => $item->id,
                'quantity'       => $item->quantity,
                'cost'           => $item->cost_price,
                'movement_type'  => 'out',
                'notes'          => 'بيع',
            ]);
        });

        static::deleting(function (SaleItem $item) {
            $warehouseId = DB::table('sales')
                ->where('id', $item->sale_id)
                ->value('warehouse_id')
                ?? \App\Services\WarehouseService::getDefault()->id;

            // Create reversal movement (append-only — NEVER delete the original)
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
                    'cost'           => $item->cost_price,
                    'movement_type'  => 'in',
                    'notes'          => 'عكس — حذف بيع',
                ]);
            }

            // Restore stock: WarehouseService updates stock_levels + recomputes products.quantity
            \App\Services\WarehouseService::in($warehouseId, $item->product_id, $item->quantity);
        });
    }
}
