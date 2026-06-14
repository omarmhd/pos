<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchasePriceList extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'is_default', 'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
    ];

    public function productPrices()
    {
        return $this->hasMany(PurchaseProductPrice::class);
    }

    public function suppliers()
    {
        return $this->hasMany(Supplier::class);
    }

    /** تكلفة صنف من هذه القائمة (null إذا غير مسعّر فيها) */
    public function costFor(int $productId): ?float
    {
        $cost = $this->productPrices()->where('product_id', $productId)->value('cost_price');
        return $cost !== null ? (float) $cost : null;
    }

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (PurchasePriceList $pl) {
            if ($pl->is_default) {
                static::where('id', '!=', $pl->id ?? 0)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });
    }
}
