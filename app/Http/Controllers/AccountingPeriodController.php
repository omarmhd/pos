<?php

namespace App\Http\Controllers;

use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\Setting;
use App\Services\PeriodLockService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountingPeriodController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:accounting.periods.view')->only(['index']);
        $this->middleware('can:accounting.periods.manage')->only(['lock', 'unlock']);
    }

    /**
     * Display the last 18 months with their status + a summary of GL activity.
     */
    public function index(Request $request)
    {
        $year     = (int) $request->input('year', now()->year);
        $currency = Setting::get('currency_symbol', 'ج.م');

        // Build 2 years of periods (current + previous)
        $periods = AccountingPeriod::lastMonths(24);

        // Attach GL summary: count of entries + total debits per period
        $glSummary = DB::table('journal_entries')
            ->selectRaw('YEAR(entry_date) as yr, MONTH(entry_date) as mo,
                         COUNT(*) as entry_count,
                         SUM((SELECT SUM(debit) FROM journal_entry_lines WHERE journal_entry_id = journal_entries.id)) as total_debit')
            ->groupBy('yr', 'mo')
            ->get()
            ->keyBy(fn($r) => $r->yr . '-' . str_pad($r->mo, 2, '0', STR_PAD_LEFT));

        $periods = $periods->map(function ($period) use ($glSummary) {
            $key = $period->year . '-' . str_pad($period->month, 2, '0', STR_PAD_LEFT);
            $gl  = $glSummary->get($key);
            $period->entry_count = $gl->entry_count ?? 0;
            $period->total_debit = $gl->total_debit ?? 0;
            return $period;
        });

        return view('accounting.periods.index', compact('periods', 'currency', 'year'));
    }

    /** Lock a period */
    public function lock(Request $request)
    {
        $request->validate([
            'year'  => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'notes' => 'nullable|string|max:500',
        ]);

        // Safety: check there are no unposted entries? Not required — just lock.
        PeriodLockService::lock(
            (int) $request->year,
            (int) $request->month,
            auth()->id(),
            $request->notes
        );

        $period = AccountingPeriod::where('year', $request->year)
            ->where('month', $request->month)
            ->first();

        return redirect()->route('accounting.periods.index')
            ->with('success', 'تم إغلاق فترة ' . ($period?->periodLabel() ?? $request->year . '-' . $request->month));
    }

    /** Unlock a period — admin only in UI */
    public function unlock(Request $request)
    {
        $request->validate([
            'year'  => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        PeriodLockService::unlock(
            (int) $request->year,
            (int) $request->month,
            auth()->id()
        );

        $period = AccountingPeriod::where('year', $request->year)
            ->where('month', $request->month)
            ->first();

        return redirect()->route('accounting.periods.index')
            ->with('success', 'تم فتح فترة ' . ($period?->periodLabel() ?? $request->year . '-' . $request->month));
    }
}
