<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * وحدة إضافية للصنف (مثل: كرتون = 12 قطعة).
 * factor = عدد الوحدات الرئيسية داخل هذه الوحدة.
 * المخزون يُخزَّن دائماً بالوحدة الرئيسية؛ التحويل يتم على مستوى سطر المستند.
 */
class ProductUnit extends Model
{
    protected $fillable = [
        'product_id', 'name', 'factor', 'barcode',
        'selling_price', 'cost_price', 'is_active',
    ];

    protected $casts = [
        'factor'        => 'decimal:4',
        'selling_price' => 'decimal:2',
        'cost_price'    => 'decimal:2',
        'is_active'     => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /** سعر بيع الوحدة: السعر المحدد أو سعر الأساس × المعامل */
    public function effectiveSellingPrice(): float
    {
        if ($this->selling_price !== null) {
            return (float) $this->selling_price;
        }
        return round((float) $this->product->selling_price * (float) $this->factor, 2);
    }

    /** تكلفة الوحدة: التكلفة المحددة أو تكلفة الأساس × المعامل */
    public function effectiveCostPrice(): float
    {
        if ($this->cost_price !== null) {
            return (float) $this->cost_price;
        }
        return round((float) $this->product->cost_price * (float) $this->factor, 2);
    }
}
