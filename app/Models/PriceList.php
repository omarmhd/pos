<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceList extends Model
{
    protected $fillable = [
        'code', 'name', 'type', 'description',
        'is_default', 'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
    ];

    public static array $types = [
        'retail'    => 'تجزئة',
        'wholesale' => 'جملة',
        'vip'       => 'VIP / عملاء مميزون',
        'staff'     => 'موظفون',
        'custom'    => 'مخصص',
    ];

    public function typeLabel(): string
    {
        return self::$types[$this->type] ?? $this->type;
    }

    public function productPrices()
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    // Ensure only one default price list
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (PriceList $pl) {
            if ($pl->is_default) {
                static::where('id', '!=', $pl->id ?? 0)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });
    }
}
