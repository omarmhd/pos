<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReturn extends Model
{
    protected $fillable = [
        'return_number',
        'purchase_id',
        'supplier_id',
        'user_id',
        'journal_entry_id',
        'return_date',
        'refund_method',
        'total_amount',
        'notes',
        'is_posted',
    ];

    protected $casts = [
        'return_date'  => 'date',
        'total_amount' => 'decimal:2',
        'is_posted'    => 'boolean',
    ];

    public function purchase()    { return $this->belongsTo(Purchase::class); }
    public function supplier()    { return $this->belongsTo(Supplier::class); }
    public function user()        { return $this->belongsTo(User::class); }
    public function items()       { return $this->hasMany(PurchaseReturnItem::class); }
    public function journalEntry(){ return $this->belongsTo(JournalEntry::class); }

    public function refundMethodLabel(): string
    {
        return match($this->refund_method) {
            'ap_deduction' => 'خصم من ذمة المورد',
            'cash'         => 'استرداد نقدي',
            'bank'         => 'استرداد بنكي',
            default        => $this->refund_method,
        };
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($ret) {
            $ret->return_number = 'PENDING-' . uniqid('', true);
        });

        static::created(function ($ret) {
            $ret->updateQuietly([
                'return_number' => 'PRET-' . date('Ymd') . '-' . str_pad((string) $ret->id, 4, '0', STR_PAD_LEFT),
            ]);
        });
    }
}
