<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ضابط كاشف لتوازن دفتر الأستاذ (تدقيق: C-1).
 *
 * بما أن أسطر القيد تُدرَج تدريجياً (حالات وسطية غير متوازنة)، لا يصلح Trigger
 * على مستوى القاعدة؛ لذا نتحقق بعد الإدراج: كل قيد يجب أن يكون Σ مدين = Σ دائن.
 *
 * الاستخدام:  php artisan accounting:check-balance
 * يُفضَّل جدولته يومياً + بعد عمليات الترحيل المجمّعة.
 */
class CheckLedgerBalance extends Command
{
    protected $signature = 'accounting:check-balance {--tolerance=0.005 : هامش التقريب المسموح}';

    protected $description = 'يتحقق من توازن كل قيود اليومية (Σ مدين = Σ دائن) ويبلّغ عن غير المتوازنة';

    public function handle(): int
    {
        $tol = (float) $this->option('tolerance');

        $unbalanced = DB::table('journal_entry_lines')
            ->select('journal_entry_id')
            ->groupBy('journal_entry_id')
            ->havingRaw('ABS(SUM(debit) - SUM(credit)) > ?', [$tol])
            ->pluck('journal_entry_id');

        if ($unbalanced->isEmpty()) {
            $this->info('✅ جميع القيود متوازنة.');
            return self::SUCCESS;
        }

        $rows = DB::table('journal_entry_lines as jel')
            ->leftJoin('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->whereIn('jel.journal_entry_id', $unbalanced)
            ->groupBy('jel.journal_entry_id', 'je.reference', 'je.entry_date')
            ->select(
                'jel.journal_entry_id',
                'je.reference',
                'je.entry_date',
                DB::raw('SUM(jel.debit) as total_debit'),
                DB::raw('SUM(jel.credit) as total_credit')
            )
            ->orderBy('jel.journal_entry_id')
            ->get();

        $this->error('❌ عُثر على ' . $rows->count() . ' قيد غير متوازن:');
        $this->table(
            ['القيد', 'المرجع', 'التاريخ', 'مدين', 'دائن', 'الفرق'],
            $rows->map(fn ($r) => [
                $r->journal_entry_id,
                $r->reference ?? '—',
                $r->entry_date ?? '—',
                number_format((float) $r->total_debit, 2),
                number_format((float) $r->total_credit, 2),
                number_format((float) $r->total_debit - (float) $r->total_credit, 2),
            ])->all()
        );

        Log::warning('Ledger balance check found unbalanced journal entries', [
            'count' => $rows->count(),
            'ids'   => $unbalanced->all(),
        ]);

        return self::FAILURE;
    }
}
