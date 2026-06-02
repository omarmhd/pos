<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerPayment extends Model
{
    protected $fillable = [
        'customer_id', 'sale_id', 'amount',
        'payment_method', 'received_at', 'notes', 'user_id',
        'branch_id',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'received_at' => 'date',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
