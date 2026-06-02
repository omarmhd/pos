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
        'total_amount',
        'payment_status',
        'paid_amount',
        'notes',
        'is_posted',
        'is_reversed',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'paid_amount'  => 'decimal:2',
        'is_posted'    => 'boolean',
        'is_reversed'  => 'boolean',
    ];

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

    public function remainingAmount()
    {
        return $this->total_amount - $this->paid_amount;
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
