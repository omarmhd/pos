<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetLine extends Model
{
    protected $fillable = [
        'budget_id', 'account_id',
        'jan','feb','mar','apr','may','jun',
        'jul','aug','sep','oct','nov','dec_',
    ];

    public function budget()  { return $this->belongsTo(Budget::class); }
    public function account() { return $this->belongsTo(Account::class); }

    private const MONTH_COLS = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec_'];

    public function totalBudget(): float
    {
        return (float) collect(self::MONTH_COLS)->sum(fn($m) => $this->$m ?? 0);
    }

    public function budgetForMonth(int $month): float
    {
        $col = self::MONTH_COLS[$month - 1] ?? null;
        return $col ? (float) ($this->$col ?? 0) : 0.0;
    }

    /** Fill all 12 months equally from an annual total */
    public function spreadAnnual(float $annual): static
    {
        $monthly = round($annual / 12);
        foreach (self::MONTH_COLS as $col) {
            $this->$col = $monthly;
        }
        return $this;
    }
}
