<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $fillable = [
        'code', 'name', 'type', 'branch_id',
        'is_default', 'is_active', 'notes',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function stockLevels()
    {
        return $this->hasMany(StockLevel::class);
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    /** Total inventory value for this warehouse: Σ(qty × cost_price) */
    public function inventoryValue(): float
    {
        return (float) $this->stockLevels()
            ->join('products', 'products.id', '=', 'stock_levels.product_id')
            ->selectRaw('SUM(stock_levels.quantity * products.cost_price) as total')
            ->value('total') ?? 0.0;
    }

    /** Display name including branch */
    public function fullName(): string
    {
        return $this->branch
            ? "{$this->branch->name} — {$this->name}"
            : $this->name;
    }

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (Warehouse $wh) {
            if (!$wh->is_default) {
                return;
            }

            $query = static::where('id', '!=', $wh->id ?? 0)
                           ->where('is_default', true);

            if ($wh->branch_id) {
                // Per-branch rule: one default per branch, other branches unaffected
                $query->where('branch_id', $wh->branch_id);
            } else {
                // No branch: one default among unassigned warehouses only
                $query->whereNull('branch_id');
            }

            $query->update(['is_default' => false]);
        });
    }
}
