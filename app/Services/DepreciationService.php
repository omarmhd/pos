<?php

namespace App\Services;

use App\Models\AssetDepreciationEntry;
use App\Models\FixedAsset;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Depreciation calculation and posting service.
 *
 * Supports:
 *   - Straight Line (القسط الثابت):    (Cost − Residual) ÷ Useful Life (months)
 *   - Declining Balance (القسط المتناقص): Net Book Value × Monthly Rate
 *
 * Idempotent: calling runForPeriod() twice for the same month is a no-op
 * because of the UNIQUE constraint on (fixed_asset_id, year, month).
 */
class DepreciationService
{
    /**
     * Build the full depreciation schedule for an asset (all future periods).
     * Returns a collection of period snapshots — no DB writes.
     *
     * @return Collection<array{year, month, label, depr, accum, nbv}>
     */
    public static function buildSchedule(FixedAsset $asset): Collection
    {
        $schedule    = collect();
        $accum       = (float) $asset->accumulated_depreciation;
        $nbv         = (float) $asset->net_book_value;
        $residual    = (float) $asset->residual_value;
        $cost        = (float) $asset->purchase_cost;
        $depreciable = max(0, $cost - $residual);

        // Start from the month AFTER purchase or the month AFTER last entry
        $lastEntry = $asset->depreciationEntries()
            ->orderByDesc('period_year')->orderByDesc('period_month')
            ->first();

        $startDate = $lastEntry
            ? Carbon::create($lastEntry->period_year, $lastEntry->period_month)->addMonth()
            : Carbon::parse($asset->purchase_date)->startOfMonth()->addMonth();

        $totalMonths = $asset->useful_life_months;
        $posted      = $asset->depreciationEntries()->count();
        $remaining   = $totalMonths - $posted;

        for ($i = 0; $i < $remaining; $i++) {
            if ($nbv <= $residual + 0.005) {
                break;
            }

            $depr = match($asset->depreciation_method) {
                'declining_balance' => self::calcDB($asset, $nbv, $residual),
                default             => self::calcSL($depreciable, $totalMonths),
            };

            // Never depreciate below residual
            $depr  = min($depr, $nbv - $residual);
            $accum = round($accum + $depr, 2);
            $nbv   = round($nbv   - $depr, 2);

            $period = $startDate->copy()->addMonths($i);

            $schedule->push([
                'year'   => $period->year,
                'month'  => $period->month,
                'label'  => self::monthLabel($period->month) . ' ' . $period->year,
                'depr'   => $depr,
                'accum'  => $accum,
                'nbv'    => $nbv,
                'posted' => false,
            ]);
        }

        return $schedule;
    }

    /**
     * Run depreciation for ONE asset for a specific month/year.
     * Posts GL entry and creates depreciation entry record.
     * Returns null if already posted or nothing to depreciate.
     */
    public static function runForAsset(FixedAsset $asset, int $year, int $month): ?AssetDepreciationEntry
    {
        if ($asset->status !== 'active') {
            return null;
        }
        if ($asset->isFullyDepreciated()) {
            $asset->update(['status' => 'fully_depreciated']);
            return null;
        }

        // Idempotency check
        $exists = AssetDepreciationEntry::where('fixed_asset_id', $asset->id)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->exists();

        if ($exists) {
            return null;
        }

        $nbv      = (float) $asset->net_book_value;
        $residual = (float) $asset->residual_value;
        $depr     = match($asset->depreciation_method) {
            'declining_balance' => self::calcDB($asset, $nbv, $residual),
            default             => self::calcSL(
                $asset->depreciableAmount(),
                $asset->useful_life_months
            ),
        };

        $depr = round(min($depr, $nbv - $residual), 2);
        if ($depr <= 0.005) {
            return null;
        }

        $accumBefore = (float) $asset->accumulated_depreciation;
        $accumAfter  = round($accumBefore + $depr, 2);
        $nbvAfter    = round($nbv - $depr, 2);

        return DB::transaction(function () use ($asset, $year, $month, $depr, $accumBefore, $accumAfter, $nbvAfter) {
            // Post GL entry
            $entry = (new LedgerPostingService())->postAssetDepreciation(
                $asset, $year, $month, $depr
            );

            // Record depreciation entry
            $depEntry = AssetDepreciationEntry::create([
                'fixed_asset_id'      => $asset->id,
                'journal_entry_id'    => $entry->id,
                'branch_id'           => $asset->branch_id,
                'period_year'         => $year,
                'period_month'        => $month,
                'depreciation_amount' => $depr,
                'accumulated_before'  => $accumBefore,
                'accumulated_after'   => $accumAfter,
                'net_book_value_after'=> $nbvAfter,
            ]);

            // Update asset running totals
            $newStatus = $nbvAfter <= (float) $asset->residual_value + 0.005
                ? 'fully_depreciated'
                : 'active';

            $asset->update([
                'accumulated_depreciation' => $accumAfter,
                'net_book_value'           => $nbvAfter,
                'status'                   => $newStatus,
            ]);

            return $depEntry;
        });
    }

    /**
     * Run depreciation for ALL active assets for a specific month/year.
     * Returns count of entries posted.
     */
    public static function runAllForPeriod(int $year, int $month): int
    {
        $assets = FixedAsset::where('status', 'active')->get();
        $count  = 0;

        foreach ($assets as $asset) {
            if (self::runForAsset($asset, $year, $month)) {
                $count++;
            }
        }

        return $count;
    }

    // ── Private calculators ──────────────────────────────────────────────────

    private static function calcSL(float $depreciable, int $lifeMonths): float
    {
        return round($depreciable / max(1, $lifeMonths), 2);
    }

    private static function calcDB(FixedAsset $asset, float $nbv, float $residual): float
    {
        $annualRate  = $asset->depreciation_rate
            ?? round(1 / max(1, $asset->useful_life_months / 12), 4);
        $monthlyRate = $annualRate / 12;
        $depr        = round($nbv * $monthlyRate, 2);
        return min($depr, max(0, $nbv - $residual));
    }

    private static function monthLabel(int $month): string
    {
        return [
            1=>'يناير',2=>'فبراير',3=>'مارس',4=>'أبريل',
            5=>'مايو',6=>'يونيو',7=>'يوليو',8=>'أغسطس',
            9=>'سبتمبر',10=>'أكتوبر',11=>'نوفمبر',12=>'ديسمبر',
        ][$month] ?? (string) $month;
    }
}
