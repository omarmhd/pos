<?php

namespace App\Http\Controllers;

use App\Services\FinancialStatementService;
use App\Services\LedgerPostingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class YearEndClosingController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:accounting.year_end_close');
    }

    public function index(Request $request, FinancialStatementService $fs)
    {
        $year = (int) $request->input('year', now()->year - 1);

        $from = Carbon::create($year, 1,  1)->startOfDay();
        $to   = Carbon::create($year, 12, 31)->endOfDay();

        $data = $fs->getIncomeStatement($from, $to);

        // Check if a closing entry already exists for this year
        $alreadyClosed = \App\Models\JournalEntry::where('reference', "CLOSE-{$year}")->exists();

        return view('accounting.year_end_closing', array_merge($data, compact('year', 'alreadyClosed')));
    }

    public function store(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        $year = (int) $request->input('year');

        // Prevent double-closing
        if (\App\Models\JournalEntry::where('reference', "CLOSE-{$year}")->exists()) {
            return back()->with('error', "تم إقفال سنة {$year} مسبقاً. لا يمكن إعادة الإقفال.");
        }

        DB::transaction(function () use ($year) {
            (new LedgerPostingService())->postYearEndClosing($year);
        });

        // Lock all 12 periods of the closed year
        \App\Services\PeriodLockService::lockYear($year, auth()->id());

        return redirect()->route('accounting.year-end-closing')
            ->with('success', "تم إقفال سنة {$year} بنجاح وترحيل قيد الإقفال، وقُفِّلت جميع الفترات المحاسبية لهذه السنة.");
    }
}
