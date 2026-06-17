<?php

namespace App\Console\Commands;

use App\Models\ServiceInvoice;
use App\Services\LedgerPostingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * قيود تسوية (Adjusting Entries) لتصحيح خرائط الحسابات التاريخية — تدقيق 2026-06-17.
 *
 * لا تُعدّل أي قيد مُرحَّل؛ بل تُنشئ قيود تصحيح جديدة بتاريخ اليوم (أثر تدقيق كامل):
 *   1) نقل رصيد ضريبة المدخلات من 1260 المكرّر إلى 1150 المعتمد، ثم تعطيل 1260.
 *   2) نقل إيراد الخدمات المسجّل خطأً في 4200 «مردودات المبيعات» إلى 4400.
 *   3) توحيد رصيد الصندوق من الحساب الأب 1000/1000.00 إلى صندوق الفرع 1000.XX
 *      حسب branch_id المسجّل في القيود.
 *
 * الاستخدام:
 *   php artisan accounting:reclassify-historical           ← مراجعة فقط (يطبع المبالغ، لا يرحّل)
 *   php artisan accounting:reclassify-historical --force    ← ترحيل قيود التسوية فعلياً
 */
class ReclassifyHistorical extends Command
{
    protected $signature   = 'accounting:reclassify-historical {--force : ترحيل قيود التسوية فعلياً بدل المراجعة فقط}';
    protected $description  = 'قيود تسوية لتصحيح ضريبة المدخلات (1260→1150) وإيراد الخدمات (4200→4400) وتوحيد الصندوق للفروع';

    public function handle(LedgerPostingService $ledger): int
    {
        $force = (bool) $this->option('force');
        $today = now()->toDateString();
        $this->info($force ? '⚙️  وضع الترحيل الفعلي' : '🔍 وضع المراجعة فقط (dry-run) — لن يُرحَّل شيء');
        $this->newLine();

        $idByCode  = fn (string $c) => DB::table('accounts')->where('code', $c)->value('id');
        $netDr     = fn ($q) => (float) $q->sum('debit') - (float) $q->sum('credit');
        $report    = [];

        DB::beginTransaction();
        try {
            // ── (1) ضريبة المدخلات: 1260 → 1150 ───────────────────────────────
            $id1260 = $idByCode('1260');
            $id1150 = $idByCode('1150');
            if ($id1260 && $id1150) {
                $net = round($netDr(DB::table('journal_entry_lines')->where('account_id', $id1260)), 2);
                if (abs($net) >= 0.01) {
                    $report[] = ['ضريبة المدخلات 1260 → 1150', number_format($net, 2)];
                    if ($force && !$this->refExists('ADJ-VAT-1260-1150')) {
                        $lines = $net > 0
                            ? [$this->ln($id1150, $net, 0, 'نقل رصيد ض.مدخلات إلى 1150'),
                               $this->ln($id1260, 0, $net, 'إقفال حساب ض.مدخلات المكرّر 1260')]
                            : [$this->ln($id1260, abs($net), 0, 'إقفال حساب ض.مدخلات المكرّر 1260'),
                               $this->ln($id1150, 0, abs($net), 'نقل رصيد ض.مدخلات إلى 1150')];
                        $ledger->buildEntryPublic($this->hdr($today, 'ADJ-VAT-1260-1150', null,
                            'تسوية: توحيد ضريبة المدخلات في الحساب 1150'), $lines);
                    }
                }
                // تعطيل 1260 بعد تفريغه
                if ($force) {
                    DB::table('accounts')->where('id', $id1260)
                        ->update(['is_active' => false, 'updated_at' => now()]);
                }
            }

            // ── (2) إيراد الخدمات: 4200 (مصدره فاتورة خدمات) → 4400 ────────────
            $id4200 = $idByCode('4200');
            $id4400 = $idByCode('4400');
            if ($id4200 && $id4400) {
                $q = DB::table('journal_entry_lines as jel')
                    ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
                    ->where('jel.account_id', $id4200)
                    ->where('je.source_type', ServiceInvoice::class);
                $svcNetCr = round((float) $q->sum('jel.credit') - (float) $q->sum('jel.debit'), 2);
                if (abs($svcNetCr) >= 0.01) {
                    $report[] = ['إيراد خدمات 4200 → 4400', number_format($svcNetCr, 2)];
                    if ($force && !$this->refExists('ADJ-SVC-4200-4400')) {
                        $lines = $svcNetCr > 0
                            ? [$this->ln($id4200, $svcNetCr, 0, 'عكس إيراد خدمات من حساب المردودات 4200'),
                               $this->ln($id4400, 0, $svcNetCr, 'إثبات إيراد خدمات في 4400')]
                            : [$this->ln($id4400, abs($svcNetCr), 0, 'تسوية إيراد خدمات 4400'),
                               $this->ln($id4200, 0, abs($svcNetCr), 'تسوية حساب المردودات 4200')];
                        $ledger->buildEntryPublic($this->hdr($today, 'ADJ-SVC-4200-4400', null,
                            'تسوية: نقل إيراد الخدمات إلى حسابه المستقل 4400'), $lines);
                    }
                }
            }

            // ── (3) الصندوق: 1000 / 1000.00 → صندوق الفرع 1000.XX ──────────────
            $parentIds = DB::table('accounts')->whereIn('code', ['1000', '1000.00'])->pluck('id')->all();
            if ($parentIds) {
                $branches = DB::table('branches')->whereNotNull('cash_account_id')
                    ->get(['id', 'name', 'cash_account_id']);
                foreach ($branches as $br) {
                    if (in_array($br->cash_account_id, $parentIds, true)) {
                        continue; // فرع صندوقه هو الأب نفسه — لا نقل
                    }
                    $ref = 'ADJ-CASH-' . $br->id;
                    $lines = [];
                    $brTotal = 0.0;
                    foreach ($parentIds as $pid) {
                        $net = round($netDr(
                            DB::table('journal_entry_lines as jel')
                                ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
                                ->where('jel.account_id', $pid)
                                ->where('je.branch_id', $br->id)
                                ->select('jel.debit', 'jel.credit')
                        ), 2);
                        if (abs($net) < 0.01) {
                            continue;
                        }
                        $brTotal += $net;
                        if ($net > 0) {
                            $lines[] = $this->ln($br->cash_account_id, $net, 0, 'توحيد صندوق الفرع – ' . $br->name);
                            $lines[] = $this->ln($pid, 0, $net, 'نقل من الصندوق العام');
                        } else {
                            $lines[] = $this->ln($pid, abs($net), 0, 'نقل من الصندوق العام');
                            $lines[] = $this->ln($br->cash_account_id, 0, abs($net), 'توحيد صندوق الفرع – ' . $br->name);
                        }
                    }
                    if ($lines) {
                        $report[] = ['صندوق الفرع: ' . $br->name, number_format($brTotal, 2)];
                        if ($force && !$this->refExists($ref)) {
                            $ledger->buildEntryPublic(
                                $this->hdr($today, $ref, $br->id, 'تسوية: توحيد رصيد صندوق الفرع ' . $br->name),
                                $lines
                            );
                        }
                    }
                }
            }

            if (!$force) {
                DB::rollBack(); // لا نُبقي أي تغيير في وضع المراجعة
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('فشل: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (!$report) {
            $this->info('لا توجد أرصدة تحتاج تسوية. ✅');
            return self::SUCCESS;
        }

        $this->table(['التسوية', 'المبلغ'], $report);
        $this->newLine();
        $this->line($force
            ? '✅ تم ترحيل قيود التسوية. راجع ميزان المراجعة للتأكد من بقائه متوازناً.'
            : '↪️  أعد التشغيل مع --force لترحيل قيود التسوية فعلياً (بعد أخذ نسخة احتياطية).');

        return self::SUCCESS;
    }

    private function ln(int $accountId, float $debit, float $credit, string $desc): array
    {
        return [
            'account_id'       => $accountId,
            'debit'            => round($debit, 2),
            'credit'           => round($credit, 2),
            'line_description' => $desc,
        ];
    }

    private function hdr(string $date, string $ref, ?int $branchId, string $desc): array
    {
        return [
            'entry_date'  => $date,
            'reference'   => $ref,
            'source_type' => null,
            'source_id'   => null,
            'branch_id'   => $branchId,
            'description' => $desc,
        ];
    }

    private function refExists(string $ref): bool
    {
        return DB::table('journal_entries')->where('reference', $ref)->exists();
    }
}
