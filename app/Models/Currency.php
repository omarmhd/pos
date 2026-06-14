<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = [
        'code', 'name', 'symbol', 'exchange_rate', 'is_base', 'is_active',
    ];

    protected $casts = [
        'exchange_rate' => 'decimal:6',
        'is_base'       => 'boolean',
        'is_active'     => 'boolean',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public static function base(): ?self
    {
        return static::where('is_base', true)->first();
    }

    /** تحويل مبلغ من هذه العملة إلى العملة الأساسية */
    public function toBase(float $amount): float
    {
        return round($amount * (float) $this->exchange_rate, 4);
    }

    protected static function boot(): void
    {
        parent::boot();

        // عملة أساسية واحدة فقط، وسعر صرفها 1 دائماً
        static::saving(function (Currency $c) {
            if ($c->is_base) {
                $c->exchange_rate = 1;
                static::where('id', '!=', $c->id ?? 0)
                    ->where('is_base', true)
                    ->update(['is_base' => false]);
            }
        });
    }
}
