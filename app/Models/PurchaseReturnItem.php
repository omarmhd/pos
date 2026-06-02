<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PurchaseReturnItem extends Model
{
    protected $fillable = [
        'purchase_return_id', 'product_id',
        'quantity', 'unit_price', 'total_price',
    ];

    protected $casts = [
        'quantity'    => 'decimal:3',
        'unit_price'  => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function purchaseReturn() { return $this->belongsTo(PurchaseReturn::class); }
    public function product()        { return $this->belongsTo(Product::class); }

    protected static function boot(): void
    {
        parent::boot();

        static::created(function (PurchaseReturnItem $item) {
            $warehouseId = DB::table('purchase_returns')
                ->where('id', $item->purchase_return_id)
                ->value('warehouse_id')
                ?? \App\Services\WarehouseService::getDefault()->id;

            StockMovement::create([
                'product_id'     => $item->product_id,
                'warehouse_id'   => $warehouseId,
                'reference_type' => static::class,
                'reference_id'   => $item->id,
                'quantity'       => $item->quantity,
                'cost'           => $item->unit_price,
                'movement_type'  => 'out',
                'notes'          => 'مرتجع مشتريات للمورد',
            ]);
            // products.quantity recomputed by WarehouseService::out() in PurchaseReturnController
        });

        static::deleting(function (PurchaseReturnItem $item) {
            $warehouseId = DB::table('purchase_returns')
                ->where('id', $item->purchase_return_id)
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
                    'movement_type'  => 'in',
                    'notes'          => 'عكس — حذف مرتجع مشتريات',
                ]);
            }

            \App\Services\WarehouseService::in($warehouseId, $item->product_id, $item->quantity);
        });
    }
}
