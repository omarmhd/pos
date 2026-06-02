<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAdjustment extends Model
{
    protected $fillable = [
        'product_id', 'warehouse_id',
        'quantity_before', 'quantity_after', 'quantity_change',
        'cost_per_unit', 'total_cost', 'reason', 'notes',
        'journal_entry_id', 'inventory_session_id', 'created_by',
    ];

    protected $casts = [
        'quantity_before' => 'decimal:3',
        'quantity_after'  => 'decimal:3',
        'quantity_change' => 'decimal:3',
        'cost_per_unit'   => 'decimal:2',
        'total_cost'      => 'decimal:2',
    ];

    public static array $reasons = [
        'shrinkage'        => 'تلف / هالك',
        'theft'            => 'سرقة',
        'damage'           => 'ضرر / كسر',
        'expiry'           => 'انتهاء صلاحية',
        'count_correction' => 'تصحيح جرد',
        'return_supplier'  => 'مرتجع مورد',
        'other'            => 'أخرى',
    ];

    public function reasonLabel(): string
    {
        return self::$reasons[$this->reason] ?? $this->reason;
    }

    public function product(): BelongsTo      { return $this->belongsTo(Product::class); }
    public function warehouse(): BelongsTo    { return $this->belongsTo(Warehouse::class); }
    public function journalEntry(): BelongsTo { return $this->belongsTo(JournalEntry::class); }
    public function session(): BelongsTo      { return $this->belongsTo(InventorySession::class, 'inventory_session_id'); }
    public function createdBy(): BelongsTo    { return $this->belongsTo(User::class, 'created_by'); }

    protected static function boot(): void
    {
        parent::boot();

        static::created(function (InventoryAdjustment $adj) {
            if (abs($adj->quantity_change) < 0.0001) {
                return;
            }

            $warehouseId = $adj->warehouse_id
                ?? \App\Services\WarehouseService::getDefault()->id;

            // Append to event log — products.quantity recomputed by WarehouseService in controller
            StockMovement::create([
                'product_id'     => $adj->product_id,
                'warehouse_id'   => $warehouseId,
                'reference_type' => static::class,
                'reference_id'   => $adj->id,
                'quantity'       => abs($adj->quantity_change),
                'cost'           => $adj->cost_per_unit,
                'movement_type'  => $adj->quantity_change >= 0 ? 'in' : 'out',
                'notes'          => $adj->reasonLabel() . ($adj->notes ? ' — ' . $adj->notes : ''),
            ]);
        });

        // Adjustments are corrective records — we allow logical deletion
        // by creating a reversal StockMovement (not deleting the original)
        static::deleting(function (InventoryAdjustment $adj) {
            if (abs($adj->quantity_change) < 0.0001) {
                return;
            }

            $warehouseId = $adj->warehouse_id
                ?? \App\Services\WarehouseService::getDefault()->id;

            $original = StockMovement::where('reference_type', static::class)
                ->where('reference_id', $adj->id)
                ->where('is_reversal', false)
                ->first();

            if ($original) {
                StockMovement::create([
                    'product_id'     => $adj->product_id,
                    'warehouse_id'   => $warehouseId,
                    'reference_type' => static::class,
                    'reference_id'   => $adj->id,
                    'reversal_of'    => $original->id,
                    'is_reversal'    => true,
                    'quantity'       => abs($adj->quantity_change),
                    'cost'           => $adj->cost_per_unit,
                    'movement_type'  => $adj->quantity_change >= 0 ? 'out' : 'in',
                    'notes'          => 'عكس — حذف تعديل: ' . $adj->reasonLabel(),
                ]);
            }

            // Restore stock via WarehouseService (updates stock_levels + products.quantity)
            if ($adj->quantity_change >= 0) {
                \App\Services\WarehouseService::out($warehouseId, $adj->product_id, abs($adj->quantity_change));
            } else {
                \App\Services\WarehouseService::in($warehouseId, $adj->product_id, abs($adj->quantity_change));
            }
        });
    }
}
