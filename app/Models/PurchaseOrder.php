<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'po_number', 'supplier_id', 'user_id', 'branch_id', 'warehouse_id',
        'order_date', 'expected_delivery_date', 'sent_at',
        'status', 'total_amount', 'terms', 'notes',
    ];

    protected $casts = [
        'order_date'               => 'date',
        'expected_delivery_date'   => 'date',
        'sent_at'                  => 'date',
        'total_amount'             => 'decimal:2',
    ];

    public static array $statuses = [
        'draft'     => ['label' => 'مسودة',          'color' => 'secondary'],
        'sent'      => ['label' => 'مُرسَل للمورد',  'color' => 'primary'],
        'partial'   => ['label' => 'مستلم جزئياً',  'color' => 'warning'],
        'received'  => ['label' => 'مستلم بالكامل', 'color' => 'success'],
        'cancelled' => ['label' => 'ملغى',           'color' => 'danger'],
    ];

    public function supplier()   { return $this->belongsTo(Supplier::class); }
    public function user()       { return $this->belongsTo(User::class); }
    public function branch()     { return $this->belongsTo(Branch::class); }
    public function warehouse()  { return $this->belongsTo(Warehouse::class); }
    public function items()      { return $this->hasMany(PurchaseOrderItem::class); }
    public function invoices()   { return $this->hasMany(Purchase::class); }

    public function statusLabel(): string
    {
        return static::$statuses[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return static::$statuses[$this->status]['color'] ?? 'secondary';
    }

    /** Total quantity received across all invoices linked to this PO */
    public function totalReceived(): float
    {
        return (float) $this->items()->sum('quantity_received');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'sent']);
    }

    public function canConvertToInvoice(): bool
    {
        return in_array($this->status, ['sent', 'partial']);
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($po) {
            $po->po_number = 'PENDING-' . uniqid('', true);
        });

        static::created(function ($po) {
            $po->updateQuietly([
                'po_number' => 'PO-' . date('Ymd') . '-' . str_pad((string) $po->id, 4, '0', STR_PAD_LEFT),
            ]);
        });
    }
}
