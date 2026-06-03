<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class SalesQuotation extends Model
{
    protected $fillable = [
        'quotation_number', 'customer_id', 'customer_name',
        'user_id', 'branch_id', 'price_list_id',
        'quotation_date', 'valid_until', 'status',
        'subtotal', 'discount_amount', 'total_amount',
        'terms', 'notes',
    ];

    protected $casts = [
        'quotation_date'  => 'date',
        'valid_until'     => 'date',
        'subtotal'        => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount'    => 'decimal:2',
    ];

    public static array $statuses = [
        'draft'    => ['label' => 'مسودة',    'color' => 'secondary'],
        'sent'     => ['label' => 'مُرسَل',   'color' => 'primary'],
        'accepted' => ['label' => 'مقبول',    'color' => 'success'],
        'rejected' => ['label' => 'مرفوض',    'color' => 'danger'],
        'expired'  => ['label' => 'منتهي',    'color' => 'warning'],
    ];

    public function customer()   { return $this->belongsTo(Customer::class); }
    public function user()       { return $this->belongsTo(User::class); }
    public function branch()     { return $this->belongsTo(Branch::class); }
    public function priceList()  { return $this->belongsTo(PriceList::class); }
    public function items()      { return $this->hasMany(SalesQuotationItem::class, 'quotation_id'); }
    public function salesOrders(){ return $this->hasMany(SalesOrder::class, 'quotation_id'); }

    public function statusLabel(): string { return static::$statuses[$this->status]['label'] ?? $this->status; }
    public function statusColor(): string { return static::$statuses[$this->status]['color'] ?? 'secondary'; }

    public function isExpired(): bool
    {
        return $this->valid_until && $this->valid_until->isPast() && $this->status === 'sent';
    }

    public function canConvert(): bool
    {
        return in_array($this->status, ['sent', 'accepted']);
    }

    public function displayName(): string
    {
        return $this->customer?->name ?? $this->customer_name ?? 'عميل غير محدد';
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($q) => $q->quotation_number = 'PENDING-' . uniqid('', true));
        static::created(fn($q) => $q->updateQuietly([
            'quotation_number' => 'QT-' . date('Ymd') . '-' . str_pad((string) $q->id, 4, '0', STR_PAD_LEFT),
        ]));
    }
}
