<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AccountingPeriod extends Model
{
    protected $fillable = [
        'year', 'month', 'status', 'notes',
        'locked_by', 'locked_at',
        'unlocked_by', 'unlocked_at',
    ];

    protected $casts = [
        'locked_at'   => 'datetime',
        'unlocked_at' => 'datetime',
    ];

    public function lockedBy()   { return $this->belongsTo(User::class, 'locked_by'); }
    public function unlockedBy() { return $this->belongsTo(User::class, 'unlocked_by'); }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }

    public function periodLabel(): string
    {
        $months = [
            1=>'يناير',2=>'فبراير',3=>'مارس',4=>'أبريل',
            5=>'مايو',6=>'يونيو',7=>'يوليو',8=>'أغسطس',
            9=>'سبتمبر',10=>'أكتوبر',11=>'نوفمبر',12=>'ديسمبر',
        ];
        return ($months[$this->month] ?? $this->month) . ' ' . $this->year;
    }

    // ── Static helpers ───────────────────────────────────────────────────────

    /**
     * Check if a specific date falls in a locked period.
     * Returns true if the period is explicitly locked in the DB.
     * Missing rows = open (by convention).
     */
    public static function isDateLocked(Carbon|string $date): bool
    {
        $d = $date instanceof Carbon ? $date : Carbon::parse($date);
        return static::where('year', $d->year)
            ->where('month', $d->month)
            ->where('status', 'locked')
            ->exists();
    }

    /**
     * Get or create the period row for a given year/month.
     */
    public static function forDate(Carbon|string $date): self
    {
        $d = $date instanceof Carbon ? $date : Carbon::parse($date);
        return static::firstOrCreate(
            ['year' => $d->year, 'month' => $d->month],
            ['status' => 'open']
        );
    }

    /**
     * Return last N months including the current month.
     * Creates rows for missing months (all default to 'open').
     */
    public static function lastMonths(int $count = 18): \Illuminate\Support\Collection
    {
        $months = collect();
        $cursor = now()->startOfMonth();

        for ($i = 0; $i < $count; $i++) {
            $months->push([
                'year'  => $cursor->year,
                'month' => $cursor->month,
            ]);
            $cursor->subMonth();
        }

        // Upsert to ensure rows exist
        foreach ($months as $m) {
            static::firstOrCreate($m, ['status' => 'open']);
        }

        return static::whereIn(
            DB::raw('CONCAT(year, "-", LPAD(month, 2, "0"))'),
            $months->map(fn($m) => $m['year'].'-'.str_pad($m['month'], 2, '0', STR_PAD_LEFT))
        )
        ->orderByDesc('year')
        ->orderByDesc('month')
        ->get();
    }
}
