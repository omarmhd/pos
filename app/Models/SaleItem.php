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
        'vat_rate', 'vat_amount', 'bonus_qty',
        'product_unit_id', 'unit_factor', 'unit_label',
    ];

    protected $casts = [
        'unit_price'  => 'decimal:2',
        'cost_price'  => 'decimal:2',
        'total_price' => 'decimal:2',
        'vat_rate'    => 'decimal:2',
        'vat_amount'  => 'decimal:2',
        'bonus_qty'   => 'decimal:3',
        'unit_factor' => 'decimal:4',
    ];

    public function productUnit() { return $this->belongsTo(ProductUnit::class); }

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

            $product = Product::with('components.component')->find($item->product_id);

            // ── خدمة: لا كارت مخزن — لا حركة مخزنية ──────────────────────────
            if ($product && $product->isService()) {
                return;
            }

            // ── تجميعي: الحركة المخزنية على المكونات وليس الصنف الأب ─────────
            if ($product && $product->isBundle()) {
                $totalQty = (float) $item->quantity + (float) ($item->bonus_qty ?? 0);
                foreach ($product->components as $comp) {
                    StockMovement::create([
                        'product_id'     => $comp->component_id,
                        'warehouse_id'   => $warehouseId,
                        'reference_type' => static::class,
                        'reference_id'   => $item->id,
                        'quantity'       => round($totalQty * (float) $comp->quantity, 4),
                        'cost'           => $comp->component->cost_price ?? 0,
                        'movement_type'  => 'out',
                        'notes'          => 'بيع صنف تجميعي — ' . $product->name,
                    ]);
                }
                return;
            }

            // ── بضاعة: الكمية الصادرة = المبيعة + البونص المجاني ─────────────
            // Append to event log — do NOT touch products.quantity here.
            // products.quantity is recomputed by WarehouseService::out() called from PosController.
            StockMovement::create([
                'product_id'     => $item->product_id,
                'warehouse_id'   => $warehouseId,
                'reference_type' => static::class,
                'reference_id'   => $item->id,
                'quantity'       => round((float) $item->quantity + (float) ($item->bonus_qty ?? 0), 4),
                'cost'           => $item->cost_price,
                'movement_type'  => 'out',
                'notes'          => 'بيع' . (($item->bonus_qty ?? 0) > 0 ? ' (يشمل بونص ' . $item->bonus_qty . ')' : ''),
            ]);
        });

        static::deleting(function (SaleItem $item) {
            $warehouseId = DB::table('sales')
                ->where('id', $item->sale_id)
                ->value('warehouse_id')
                ?? \App\Services\WarehouseService::getDefault()->id;

            // Create reversal movements (append-only — NEVER delete the originals).
            // يشمل حركات المكونات في حالة الصنف التجميعي.
            $originals = StockMovement::where('reference_type', static::class)
                ->where('reference_id', $item->id)
                ->where('is_reversal', false)
                ->get();

            foreach ($originals as $original) {
                StockMovement::create([
                    'product_id'     => $original->product_id,
                    'warehouse_id'   => $warehouseId,
                    'reference_type' => static::class,
                    'reference_id'   => $item->id,
                    'reversal_of'    => $original->id,
                    'is_reversal'    => true,
                    'quantity'       => $original->quantity,
                    'cost'           => $original->cost,
                    'movement_type'  => 'in',
                    'notes'          => 'عكس — حذف بيع',
                ]);

                // Restore stock: WarehouseService updates stock_levels + recomputes products.quantity
                \App\Services\WarehouseService::in($warehouseId, $original->product_id, (float) $original->quantity);
            }
        });
    }
}
