<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Branch;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Setting;
use App\Services\PeriodLockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OpeningBalanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:journal_entries.create');
    }

    public function index()
    {
        // Diagnose current balance sheet state
        $fs       = new \App\Services\FinancialStatementService();
        $asOf     = now();
        $ytdFrom  = $asOf->copy()->startOfYear();
        $ni       = $fs->getIncomeStatement($ytdFrom, $asOf)['netIncome'];
        $bs       = $fs->getBalanceSheet($asOf, $ni);

        $currency = Setting::get('currency_symbol', 'ج.م');
        $cashCode = Setting::get('account_cash_code', '1000');
        $capCode  = '3000';

        $cashAccount    = Account::where('code', $cashCode)->first();
        $capitalAccount = Account::where('code', $capCode)->first();

        // All leaf asset accounts for the opening-balance form
        $assetAccounts = Account::where('type', 'asset')
            ->where('is_header', false)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $equityAccounts = Account::where('type', 'equity')
            ->where('is_header', false)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        // Check if an opening entry already exists
        $hasOpening = JournalEntry::where('reference', 'LIKE', 'OB-%')->exists();

        return view('accounting.opening-balance', compact(
            'bs', 'ni', 'currency',
            'cashAccount', 'capitalAccount',
            'assetAccounts', 'equityAccounts',
            'hasOpening'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'entry_date'               => 'required|date',
            'lines'                    => 'required|array|min:2',
            'lines.*.account_id'       => 'required|exists:accounts,id',
            'lines.*.debit'            => 'required|numeric|min:0',
            'lines.*.credit'           => 'required|numeric|min:0',
            'lines.*.line_description' => 'nullable|string|max:255',
        ]);

        // Same guards as manual JE
        $totalDebit  = round(collect($request->lines)->sum('debit'),  2);
        $totalCredit = round(collect($request->lines)->sum('credit'), 2);

        if (abs($totalDebit - $totalCredit) > 0.005) {
            return back()->withInput()
                ->withErrors(['lines' => "القيد غير متوازن — مدين: {$totalDebit} / دائن: {$totalCredit}"]);
        }

        if ($totalDebit <= 0) {
            return back()->withInput()
                ->withErrors(['lines' => 'يجب أن تكون المبالغ أكبر من صفر']);
        }

        $accountIds  = collect($request->lines)->pluck('account_id')->unique();
        if (Account::whereIn('id', $accountIds)->where('is_header', true)->exists()) {
            return back()->withInput()
                ->withErrors(['lines' => 'لا يمكن الترحيل على حساب رئيسي (تجميعي)']);
        }

        try {
            PeriodLockService::assertOpen($request->entry_date);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        DB::transaction(function () use ($request) {
            $branchId = $this->effectiveBranchId($request);

            $entry = JournalEntry::create([
                'entry_date'  => $request->entry_date,
                'description' => 'قيد الأرصدة الافتتاحية',
                'reference'   => 'OB-' . date('Y') . '-' . now()->format('mdHis'),
                'source_type' => null,
                'source_id'   => null,
                'branch_id'   => $branchId,
                'user_id'     => auth()->id(),
                'posted_at'   => now(),
            ]);

            foreach ($request->lines as $line) {
                if ((float)$line['debit'] == 0 && (float)$line['credit'] == 0) continue;
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $line['account_id'],
                    'debit'            => round((float) $line['debit'],  2),
                    'credit'           => round((float) $line['credit'], 2),
                    'line_description' => $line['line_description'] ?? 'أرصدة افتتاحية',
                ]);
            }
        });

        return redirect()->route('accounting.balance-sheet')
            ->with('success', 'تم ترحيل قيد الأرصدة الافتتاحية بنجاح — تحقق من الميزانية العمومية');
    }
}
