<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAdjustment extends Model
{
    protected $fillable = [
        'product_id', 'quantity_before', 'quantity_after', 'quantity_change',
        'cost_per_unit', 'total_cost', 'reason', 'notes',
        'journal_entry_id', 'inventory_session_id', 'created_by',
    ];

    protected $casts = [
        'quantity_before'  => 'decimal:3',
        'quantity_after'   => 'decimal:3',
        'quantity_change'  => 'decimal:3',
        'cost_per_unit'    => 'decimal:2',
        'total_cost'       => 'decimal:2',
    ];

    public static array $reasons = [
        'shrinkage'        => 'تلف / هالك',
        'theft'            => 'سرقة',
        'damage'           => 'ضرر / كسر',
        'expiry'           => 'انتهاء صلاحية',
        'count_correction' => 'تصحيح جرد',
        'return_supplier'  => 'مرتجع مورد',
        'other'            => 'أخرى',
    ];

    public function reasonLabel(): string
    {
        return self::$reasons[$this->reason] ?? $this->reason;
    }

    public function product(): BelongsTo   { return $this->belongsTo(Product::class); }
    public function journalEntry(): BelongsTo { return $this->belongsTo(JournalEntry::class); }
    public function session(): BelongsTo   { return $this->belongsTo(InventorySession::class, 'inventory_session_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
