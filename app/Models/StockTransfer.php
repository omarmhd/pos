<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Stock Transfer — تحويل مخزون داخلي
 *
 * Moves inventory between warehouses within the same company.
 * Most common: Backroom (مخزن خلفي) → Floor/Display (معرض/رفوف)
 *
 * IMPORTANT: No GL journal entry is created.
 * Total inventory value (Account 1300) is unchanged.
 * Only stock_levels per-warehouse change.
 *
 * Audit trail: StockMovement records are created for each item:
 *   movement_type='out' in from_warehouse
 *   movement_type='in'  in to_warehouse
 */
class StockTransfer extends Model
{
    protected $fillable = [
        'transfer_number', 'from_warehouse_id', 'to_warehouse_id',
        'branch_id', 'user_id', 'transfer_date',
        'status', 'notes', 'completed_at',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'completed_at'  => 'datetime',
    ];

    public static array $statuses = [
        'draft'     => ['label' => 'مسودة',   'color' => 'secondary'],
        'completed' => ['label' => 'مكتمل',   'color' => 'success'],
        'cancelled' => ['label' => 'ملغى',    'color' => 'danger'],
    ];

    public function fromWarehouse() { return $this->belongsTo(Warehouse::class, 'from_warehouse_id'); }
    public function toWarehouse()   { return $this->belongsTo(Warehouse::class, 'to_warehouse_id'); }
    public function branch()        { return $this->belongsTo(Branch::class); }
    public function user()          { return $this->belongsTo(User::class); }
    public function items()         { return $this->hasMany(StockTransferItem::class); }

    public function statusLabel(): string { return static::$statuses[$this->status]['label'] ?? $this->status; }
    public function statusColor(): string { return static::$statuses[$this->status]['color'] ?? 'secondary'; }

    public function totalValue(): float
    {
        return (float) $this->items()->selectRaw('SUM(quantity * unit_cost) as total')->value('total') ?? 0.0;
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($t) => $t->transfer_number = 'PENDING-' . uniqid('', true));
        static::created(fn($t) => $t->updateQuietly([
            'transfer_number' => 'TRF-' . date('Ymd') . '-' . str_pad((string) $t->id, 4, '0', STR_PAD_LEFT),
        ]));
    }
}
