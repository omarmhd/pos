<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'barcode',
        'category_id',
        'description',
        'cost_price',
        'selling_price',
        'quantity',
        'min_quantity',
        'unit',
        'expiry_date',
        'image',
        'inventory_account_id',
        'cogs_account_id'
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function isLowStock()
    {
        return $this->quantity <= $this->min_quantity;
    }

    public function isExpiringSoon($days = 30)
    {
        if (!$this->expiry_date) return false;
        return $this->expiry_date->diffInDays(now()) <= $days && $this->expiry_date->isFuture();
    }

    public function isExpired()
    {
        if (!$this->expiry_date) return false;
        return $this->expiry_date->isPast();
    }
}
