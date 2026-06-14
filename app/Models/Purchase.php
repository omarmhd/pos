<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'supplier_invoice_number',
        'supplier_id',
        'user_id',
        'warehouse_id',
        'branch_id',
        'purchase_order_id',
        'total_amount',
        'tax_amount',
        'payment_status',
        'paid_amount',
        'notes',
        'is_posted',
        'is_reversed',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'tax_amount'   => 'decimal:2',
        'paid_amount'  => 'decimal:2',
        'is_posted'    => 'boolean',
        'is_reversed'  => 'boolean',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function returns()
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    public function remainingAmount()
    {
        $returned = (float) $this->returns()->where('refund_method', 'ap_deduction')->sum('total_amount');
        return max(0, $this->total_amount - $this->paid_amount - $returned);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($purchase) {
            $purchase->invoice_number = 'PENDING-' . uniqid('', true);
        });

        static::created(function ($purchase) {
            $purchase->updateQuietly([
                'invoice_number' => 'PUR-' . date('Ymd') . '-' . str_pad((string) $purchase->id, 4, '0', STR_PAD_LEFT),
            ]);
        });
    }
}
