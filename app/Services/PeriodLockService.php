<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use Carbon\Carbon;
use RuntimeException;

/**
 * Central service for accounting period lock enforcement.
 *
 * All GL posting methods call assertOpen() before writing.
 * This is the single point of enforcement — no scattered checks.
 */
class PeriodLockService
{
    /**
     * Assert that the given date's accounting period is open.
     * Throws RuntimeException if the period is locked.
     *
     * @param  Carbon|string $date  The journal entry date to check.
     * @throws RuntimeException    If the period is locked.
     */
    public static function assertOpen(Carbon|string $date): void
    {
        $d = $date instanceof Carbon ? $date : Carbon::parse($date);

        if (AccountingPeriod::isDateLocked($d)) {
            $monthName = self::monthName($d->month);
            throw new RuntimeException(
                "الفترة المحاسبية [{$monthName} {$d->year}] مقفلة — لا يمكن ترحيل قيود في فترة مقفلة. "
                . "يرجى طلب فتح الفترة من المحاسب المسؤول."
            );
        }
    }

    /**
     * Lock a period. Only one call needed — idempotent.
     */
    public static function lock(int $year, int $month, int $userId, ?string $notes = null): AccountingPeriod
    {
        $period = AccountingPeriod::firstOrCreate(
            ['year' => $year, 'month' => $month],
            ['status' => 'open']
        );

        if ($period->status === 'locked') {
            return $period; // Already locked — idempotent
        }

        $period->update([
            'status'    => 'locked',
            'locked_by' => $userId,
            'locked_at' => now(),
            'notes'     => $notes,
        ]);

        return $period;
    }

    /**
     * Unlock a period. Restricted to admins in the controller layer.
     */
    public static function unlock(int $year, int $month, int $userId): AccountingPeriod
    {
        $period = AccountingPeriod::firstOrCreate(
            ['year' => $year, 'month' => $month],
            ['status' => 'open']
        );

        $period->update([
            'status'       => 'open',
            'unlocked_by'  => $userId,
            'unlocked_at'  => now(),
        ]);

        return $period;
    }

    /**
     * Lock all periods for a fiscal year (called from year-end closing).
     */
    public static function lockYear(int $year, int $userId): int
    {
        $count = 0;
        for ($month = 1; $month <= 12; $month++) {
            $period = static::lock($year, $month, $userId, "إغلاق سنة {$year}");
            if ($period->wasRecentlyCreated || $period->wasChanged()) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Summary of period status for a given year.
     *
     * @return array<int, bool>  [month => isLocked]
     */
    public static function yearSummary(int $year): array
    {
        $periods = AccountingPeriod::where('year', $year)->get()->keyBy('month');
        $result  = [];
        for ($m = 1; $m <= 12; $m++) {
            $result[$m] = isset($periods[$m]) && $periods[$m]->status === 'locked';
        }
        return $result;
    }

    private static function monthName(int $month): string
    {
        return [
            1=>'يناير',2=>'فبراير',3=>'مارس',4=>'أبريل',
            5=>'مايو',6=>'يونيو',7=>'يوليو',8=>'أغسطس',
            9=>'سبتمبر',10=>'أكتوبر',11=>'نوفمبر',12=>'ديسمبر',
        ][$month] ?? (string) $month;
    }
}
