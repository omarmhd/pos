<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostPriceHistory extends Model
{
    // اسم الجدول الفعلي (مفرد) كما في الترحيل — وإلا بحث Eloquent عن cost_price_histories
    protected $table = 'cost_price_history';

    protected $fillable = [
        'product_id', 'old_cost', 'new_cost', 'qty_received',
        'method', 'reference_type', 'reference_id',
        'changed_by', 'notes',
    ];

    protected $casts = [
        'old_cost'     => 'decimal:4',
        'new_cost'     => 'decimal:4',
        'qty_received' => 'decimal:3',
    ];

    public function product()   { return $this->belongsTo(Product::class); }
    public function changedBy() { return $this->belongsTo(User::class, 'changed_by'); }

    public function methodLabel(): string
    {
        return match($this->method) {
            'avco'   => 'AVCO (متوسط متحرك)',
            'manual' => 'تعديل يدوي',
            'import' => 'استيراد CSV',
            default  => $this->method,
        };
    }
}
