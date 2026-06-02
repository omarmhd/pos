<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SaleReturnItem extends Model
{
    protected $fillable = [
        'sale_return_id', 'product_id',
        'quantity', 'unit_price', 'cost_price', 'total_price',
    ];

    protected $casts = [
        'quantity'    => 'decimal:3',
        'unit_price'  => 'decimal:2',
        'cost_price'  => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function saleReturn() { return $this->belongsTo(SaleReturn::class); }
    public function product()    { return $this->belongsTo(Product::class); }

    protected static function boot(): void
    {
        parent::boot();

        static::created(function (SaleReturnItem $item) {
            $warehouseId = DB::table('sale_returns')
                ->where('id', $item->sale_return_id)
                ->value('warehouse_id')
                ?? \App\Services\WarehouseService::getDefault()->id;

            StockMovement::create([
                'product_id'     => $item->product_id,
                'warehouse_id'   => $warehouseId,
                'reference_type' => static::class,
                'reference_id'   => $item->id,
                'quantity'       => $item->quantity,
                'cost'           => $item->cost_price,
                'movement_type'  => 'in',
                'notes'          => 'مرتجع مبيعات',
            ]);
            // products.quantity recomputed by WarehouseService::in() in SaleReturnController
        });

        static::deleting(function (SaleReturnItem $item) {
            $warehouseId = DB::table('sale_returns')
                ->where('id', $item->sale_return_id)
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
                    'cost'           => $item->cost_price,
                    'movement_type'  => 'out',
                    'notes'          => 'عكس — حذف مرتجع مبيعات',
                ]);
            }

            \App\Services\WarehouseService::out($warehouseId, $item->product_id, $item->quantity);
        });
    }
}
