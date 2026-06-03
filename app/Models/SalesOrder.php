<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    protected $fillable = [
        'order_number', 'quotation_id', 'customer_id',
        'user_id', 'branch_id', 'warehouse_id', 'price_list_id',
        'order_date', 'expected_delivery_date',
        'status', 'is_credit',
        'total_amount', 'terms', 'notes',
    ];

    protected $casts = [
        'order_date'              => 'date',
        'expected_delivery_date'  => 'date',
        'total_amount'            => 'decimal:2',
        'is_credit'               => 'boolean',
    ];

    public static array $statuses = [
        'draft'     => ['label' => 'مسودة',            'color' => 'secondary'],
        'confirmed' => ['label' => 'مؤكد',             'color' => 'primary'],
        'partial'   => ['label' => 'مُسلَّم جزئياً',  'color' => 'warning'],
        'fulfilled' => ['label' => 'مُسلَّم بالكامل', 'color' => 'success'],
        'cancelled' => ['label' => 'ملغى',             'color' => 'danger'],
    ];

    public function quotation()  { return $this->belongsTo(SalesQuotation::class, 'quotation_id'); }
    public function customer()   { return $this->belongsTo(Customer::class); }
    public function user()       { return $this->belongsTo(User::class); }
    public function branch()     { return $this->belongsTo(Branch::class); }
    public function warehouse()  { return $this->belongsTo(Warehouse::class); }
    public function priceList()  { return $this->belongsTo(PriceList::class); }
    public function items()      { return $this->hasMany(SalesOrderItem::class); }
    public function invoices()   { return $this->hasMany(Sale::class, 'sales_order_id'); }

    public function statusLabel(): string { return static::$statuses[$this->status]['label'] ?? $this->status; }
    public function statusColor(): string { return static::$statuses[$this->status]['color'] ?? 'secondary'; }

    public function canConvertToInvoice(): bool
    {
        return in_array($this->status, ['confirmed', 'partial']);
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($o) => $o->order_number = 'PENDING-' . uniqid('', true));
        static::created(fn($o) => $o->updateQuietly([
            'order_number' => 'SO-' . date('Ymd') . '-' . str_pad((string) $o->id, 4, '0', STR_PAD_LEFT),
        ]));
    }
}
