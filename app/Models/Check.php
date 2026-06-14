<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Check extends Model
{
    use SoftDeletes;

    protected $table = 'checks';

    protected $fillable = [
        'check_number', 'check_ref', 'type', 'check_date', 'due_date',
        'amount', 'currency_id', 'exchange_rate', 'foreign_amount',
        'bank_name', 'bank_branch', 'account_number',
        'customer_id', 'supplier_id', 'endorsed_to_supplier_id', 'party_name',
        'status', 'notes', 'branch_id', 'user_id',
        'journal_entry_id', 'deposit_journal_entry_id', 'clearing_journal_entry_id',
    ];

    protected $casts = [
        'check_date'     => 'date',
        'due_date'       => 'date',
        'amount'         => 'decimal:2',
        'exchange_rate'  => 'decimal:6',
        'foreign_amount' => 'decimal:2',
    ];

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(fn($c) => $c->check_number = 'PENDING-' . uniqid());

        static::created(function ($c) {
            $prefix = $c->type === 'receivable' ? 'CHK-IN' : 'CHK-OUT';
            $c->updateQuietly([
                'check_number' => $prefix . '-' . date('Ymd') . '-' . str_pad($c->id, 4, '0', STR_PAD_LEFT),
            ]);
        });
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function endorsedToSupplier()
    {
        return $this->belongsTo(Supplier::class, 'endorsed_to_supplier_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function depositJournalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'deposit_journal_entry_id');
    }

    public function clearingJournalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'clearing_journal_entry_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function typeLabel(): string
    {
        return $this->type === 'receivable' ? 'وارد (من عميل)' : 'صادر (لمورد)';
    }

    public function typeBadge(): string
    {
        return $this->type === 'receivable'
            ? '<span class="badge bg-success">وارد</span>'
            : '<span class="badge bg-danger">صادر</span>';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'received'  => 'تحت التحصيل',
            'deposited' => 'مودَع في البنك',
            'cleared'   => 'مُقاصّ',
            'bounced'   => 'مرتجع / ملتوي',
            'endorsed'  => 'مُجيَّر (لمورد)',
            'pending'   => 'بانتظار الصرف',
            'returned'  => 'أُعيد للمورد',
            default     => $this->status,
        };
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'received'  => '<span class="badge bg-info text-dark">تحت التحصيل</span>',
            'deposited' => '<span class="badge bg-primary">مودَع</span>',
            'cleared'   => '<span class="badge bg-success">مُقاصّ</span>',
            'bounced'   => '<span class="badge bg-danger">مرتجع</span>',
            'endorsed'  => '<span class="badge bg-dark">مُجيَّر</span>',
            'pending'   => '<span class="badge bg-warning text-dark">بانتظار الصرف</span>',
            'returned'  => '<span class="badge bg-secondary">أُعيد</span>',
            default     => '<span class="badge bg-light text-dark">' . e($this->status) . '</span>',
        };
    }

    /** الانتقالات المسموح بها لكل حالة */
    public function allowedTransitions(): array
    {
        return match ($this->status) {
            'received'  => ['deposited', 'bounced', 'endorsed'],
            'deposited' => ['cleared', 'bounced'],
            'bounced'   => ['received'],   // إعادة إيداع شيك مرتدّ (re-present)
            'pending'   => ['cleared', 'returned'],
            default     => [],   // cleared, returned, endorsed = terminal
        };
    }

    public function isTerminal(): bool
    {
        // bounced لم يعد نهائياً — يمكن إعادة إيداعه
        return in_array($this->status, ['cleared', 'returned', 'endorsed']);
    }

    public function partyName(): string
    {
        return $this->customer?->name ?? $this->supplier?->name ?? $this->party_name ?? '—';
    }
}
