<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * POS Terminal — نقطة البيع
 *
 * Each terminal is assigned to a specific WAREHOUSE.
 * This warehouse is WHERE inventory is deducted when a sale is made.
 *
 * Mall setup example:
 *   Terminal "Cashier 1 — Display Floor" → warehouse_id = WH-FLOOR (معرض)
 *   Terminal "Cashier 2 — Backroom POS"  → warehouse_id = WH-MAIN (مخزن خلفي)
 *
 * Resolution priority in WarehouseService:
 *   user.pos_terminal.warehouse_id > user.branch.default_warehouse > system_default
 */
class PosTerminal extends Model
{
    protected $fillable = [
        'code', 'name', 'branch_id', 'warehouse_id',
        'price_list_id', 'is_active', 'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function branch()    { return $this->belongsTo(Branch::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function priceList() { return $this->belongsTo(PriceList::class); }
    public function users()     { return $this->hasMany(User::class, 'pos_terminal_id'); }

    public function locationSummary(): string
    {
        $parts = [];
        if ($this->branch)    $parts[] = $this->branch->name;
        if ($this->warehouse) $parts[] = $this->warehouse->name;
        return implode(' — ', $parts) ?: '—';
    }
}
