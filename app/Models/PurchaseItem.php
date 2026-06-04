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

            // ── AVCO (Weighted Average Cost / المتوسط المتحرك) ───────────────
            // Formula: new_cost = (old_qty × old_cost + new_qty × new_cost) / total_qty
            //
            // products.quantity is still the OLD total here because WarehouseService::in()
            // is called AFTER PurchaseItem::create() in PurchaseController::store().
            // This makes PurchaseItem::created() the perfect place for AVCO.
            //
            // Standard: IAS 2 §25 allows AVCO (Weighted Average Method).

            $product = Product::lockForUpdate()->find($item->product_id);
            $oldQty  = max(0, (float) $product->quantity);
            $oldCost = (float) $product->cost_price;
            $newQty  = (float) $item->quantity;
            $newCost = (float) $item->unit_price;

            if ($oldQty + $newQty > 0.0001) {
                $avco = round(($oldQty * $oldCost + $newQty * $newCost) / ($oldQty + $newQty), 4);
            } else {
                $avco = $newCost;
            }

            // Write history before updating
            if (abs($avco - $oldCost) > 0.0001) {
                \App\Models\CostPriceHistory::create([
                    'product_id'     => $item->product_id,
                    'old_cost'       => $oldCost,
                    'new_cost'       => $avco,
                    'qty_received'   => $newQty,
                    'method'         => 'avco',
                    'reference_type' => static::class,
                    'reference_id'   => $item->id,
                    'changed_by'     => auth()->id(),
                    'notes'          => 'AVCO: (' . number_format($oldQty, 2) . ' × ' . number_format($oldCost, 4)
                                       . ' + ' . number_format($newQty, 2) . ' × ' . number_format($newCost, 4)
                                       . ') ÷ ' . number_format($oldQty + $newQty, 2),
                ]);
            }

            $product->update(['cost_price' => $avco]);

            // Append to event log. products.quantity recomputed by WarehouseService::in() in controller.
            StockMovement::create([
                'product_id'     => $item->product_id,
                'warehouse_id'   => $warehouseId,
                'reference_type' => static::class,
                'reference_id'   => $item->id,
                'quantity'       => $item->quantity,
                'cost'           => $avco,     // record the AVCO cost, not just the purchase price
                'movement_type'  => 'in',
                'notes'          => 'استلام بضاعة — AVCO: ' . number_format($avco, 4),
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
