<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * مستند تصنيع/تجميع: يستهلك المكونات (صادر) وينتج الصنف النهائي (وارد)
 * بتكلفة = مجموع تكلفة المكونات المستهلكة.
 */
class Assembly extends Model
{
    protected $fillable = [
        'number', 'assembly_date', 'product_id', 'warehouse_id',
        'quantity', 'unit_cost', 'total_cost', 'notes', 'user_id', 'is_posted',
    ];

    protected $casts = [
        'assembly_date' => 'date',
        'quantity'      => 'decimal:3',
        'unit_cost'     => 'decimal:4',
        'total_cost'    => 'decimal:2',
        'is_posted'     => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(AssemblyItem::class);
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Assembly $a) {
            if (empty($a->number)) {
                $a->number = 'PENDING-' . uniqid();
            }
        });

        static::created(function (Assembly $a) {
            if (str_starts_with($a->number, 'PENDING-')) {
                $a->updateQuietly([
                    'number' => sprintf('ASM-%s-%04d', now()->format('Ymd'), $a->id % 10000),
                ]);
            }
        });
    }
}
