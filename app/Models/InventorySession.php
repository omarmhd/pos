<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventorySession extends Model
{
    protected $fillable = [
        'reference', 'title', 'status',
        'warehouse_id',              // ← which warehouse is being counted
        'category_id', 'notes',
        'started_at', 'approved_at', 'approved_by', 'journal_entry_id', 'created_by',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'approved_at' => 'datetime',
    ];

    public static array $statuses = [
        'draft'       => 'مسودة',
        'in_progress' => 'جارٍ الجرد',
        'approved'    => 'معتمد',
        'cancelled'   => 'ملغي',
    ];

    public static array $statusColors = [
        'draft'       => 'secondary',
        'in_progress' => 'warning',
        'approved'    => 'success',
        'cancelled'   => 'danger',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->reference)) {
                $now = now();
                $seq = static::whereYear('created_at', $now->year)
                             ->whereMonth('created_at', $now->month)
                             ->count() + 1;
                $model->reference = 'INV-' . $now->format('Ym') . '-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
            }
        });
    }

    public function statusLabel(): string  { return self::$statuses[$this->status] ?? $this->status; }
    public function statusColor(): string  { return self::$statusColors[$this->status] ?? 'secondary'; }

    public function warehouse(): BelongsTo    { return $this->belongsTo(Warehouse::class); }
    public function items(): HasMany         { return $this->hasMany(InventorySessionItem::class, 'session_id'); }
    public function adjustments(): HasMany   { return $this->hasMany(InventoryAdjustment::class, 'inventory_session_id'); }
    public function journalEntry(): BelongsTo { return $this->belongsTo(JournalEntry::class); }
    public function approvedBy(): BelongsTo  { return $this->belongsTo(User::class, 'approved_by'); }
    public function createdBy(): BelongsTo   { return $this->belongsTo(User::class, 'created_by'); }

    public function totalVariance(): float
    {
        return (float) $this->items()->whereNotNull('counted_quantity')->sum('difference');
    }

    public function totalVarianceValue(): float
    {
        return (float) $this->items()
            ->whereNotNull('counted_quantity')
            ->selectRaw('SUM(difference * cost_per_unit) as val')
            ->value('val') ?? 0;
    }

    public function countedItemsCount(): int
    {
        return $this->items()->whereNotNull('counted_quantity')->count();
    }
}
