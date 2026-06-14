<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * فاتورة إيراد الخدمات (Service Revenue Invoice — IFRS 15).
 * تقابل فاتورة المصاريف لكن على جانب الإيراد: تثبت إيراد خدمة + ضريبة مخرجات + ذمة/تحصيل العميل،
 * وتُدرَج في كشف الإيرادات والمصروفات بعضوية حصرية.
 */
class ServiceInvoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'invoice_number', 'invoice_date',
        'customer_id', 'customer_name',
        'service_account_id', 'description',
        'total_amount', 'tax_amount',
        'is_credit', 'is_posted', 'journal_entry_id',
        'user_id', 'branch_id', 'res_statement_id',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'total_amount' => 'decimal:2',
        'tax_amount'   => 'decimal:2',
        'is_credit'    => 'boolean',
        'is_posted'    => 'boolean',
    ];

    /** صافي الإيراد (بلا ضريبة) */
    public function netAmount(): float
    {
        return round((float) $this->total_amount - (float) $this->tax_amount, 2);
    }

    public function customer()       { return $this->belongsTo(Customer::class); }
    public function serviceAccount() { return $this->belongsTo(Account::class, 'service_account_id'); }
    public function journalEntry()   { return $this->belongsTo(JournalEntry::class); }
    public function user()           { return $this->belongsTo(User::class); }
    public function branch()         { return $this->belongsTo(Branch::class); }
    public function resStatement()   { return $this->belongsTo(RevenueExpenseStatement::class, 'res_statement_id'); }

    public function partyName(): string
    {
        return $this->customer?->name ?? $this->customer_name ?? '—';
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $s) {
            $s->invoice_number = 'PENDING-' . uniqid('', true);
        });

        static::created(function (self $s) {
            $s->updateQuietly([
                'invoice_number' => 'SRV-' . date('Ymd') . '-' . str_pad((string) $s->id, 4, '0', STR_PAD_LEFT),
            ]);
        });
    }
}
