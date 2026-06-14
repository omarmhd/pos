<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * معادلة التصنيع / مكونات الصنف التجميعي.
 * quantity = الكمية المستهلكة من المكوّن لإنتاج/بيع وحدة واحدة من الصنف الأب.
 */
class ProductComponent extends Model
{
    protected $fillable = ['product_id', 'component_id', 'quantity'];

    protected $casts = ['quantity' => 'decimal:4'];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function component()
    {
        return $this->belongsTo(Product::class, 'component_id');
    }
}
