<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashShift extends Model
{
    protected $fillable = [
        'branch_id', 'user_id', 'closed_by', 'pos_terminal_id', 'journal_entry_id',
        'status', 'opened_at', 'closed_at',
        'opening_amount', 'cash_sales', 'cash_returns',
        'expected_amount', 'closing_amount', 'variance_amount',
        'variance_reason', 'notes',
    ];

    protected $casts = [
        'opened_at'       => 'datetime',
        'closed_at'       => 'datetime',
        'opening_amount'  => 'decimal:2',
        'cash_sales'      => 'decimal:2',
        'cash_returns'    => 'decimal:2',
        'expected_amount' => 'decimal:2',
        'closing_amount'  => 'decimal:2',
        'variance_amount' => 'decimal:2',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function branch()      { return $this->belongsTo(Branch::class); }
    public function user()        { return $this->belongsTo(User::class); }
    public function closedBy()    { return $this->belongsTo(User::class, 'closed_by'); }
    public function posTerminal() { return $this->belongsTo(PosTerminal::class); }
    public function journalEntry(){ return $this->belongsTo(JournalEntry::class); }
    public function sales()       { return $this->hasMany(Sale::class); }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeOpen($query)   { return $query->where('status', 'open'); }
    public function scopeClosed($query) { return $query->where('status', 'closed'); }

    // ── Computed helpers ─────────────────────────────────────────────────────

    /** Returns the currently open shift for a user (null if none) */
    public static function activeForUser(int $userId): ?self
    {
        return static::where('user_id', $userId)->where('status', 'open')->latest()->first();
    }

    /** Calculate expected amount from actual sales linked to this shift */
    public function recalculate(): static
    {
        $cashSales = $this->sales()
            ->where('payment_method', 'cash')
            ->where('is_posted', true)
            ->sum('total_amount');

        // TODO: deduct cash sale returns when SaleReturn has shift_id
        $cashReturns = 0;

        $this->cash_sales    = round((float) $cashSales, 2);
        $this->cash_returns  = round((float) $cashReturns, 2);
        $this->expected_amount = round(
            (float) $this->opening_amount + $this->cash_sales - $this->cash_returns,
            2
        );

        return $this;
    }

    /** Label for variance direction */
    public function varianceLabel(): string
    {
        if (is_null($this->variance_amount)) return '—';
        if (abs((float) $this->variance_amount) < 0.005) return 'متطابق';
        return (float) $this->variance_amount > 0 ? 'زيادة' : 'نقص';
    }

    public function varianceColor(): string
    {
        if (is_null($this->variance_amount)) return 'secondary';
        if (abs((float) $this->variance_amount) < 0.005) return 'success';
        return (float) $this->variance_amount > 0 ? 'info' : 'danger';
    }

    public function durationLabel(): string
    {
        if (!$this->closed_at) {
            return $this->opened_at->diffForHumans(null, true);
        }
        return $this->opened_at->diff($this->closed_at)->format('%H:%I:%S');
    }
}
