<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Setting;
use Illuminate\Console\Command;

/**
 * التحقق من خريطة الحسابات قبل الترحيل (تدقيق H-1 + H-2).
 *
 * 1) لكل مفتاح في config/account_codes.php: يتأكد أن الحساب موجود ونشط وغير رئيسي.
 * 2) يحذّر من حسابات الميزانية (أصل/التزام/حقوق) بلا تصنيف فرعي (sub_type)،
 *    لأنها تُصنَّف في القوائم المالية حسب نطاق الكود (heuristic) وقد يكون خاطئاً.
 *
 * الاستخدام: php artisan accounts:verify
 */
class VerifyAccounts extends Command
{
    protected $signature = 'accounts:verify';

    protected $description = 'يتحقق من وجود ونشاط كل حساب مطلوب، ويحذّر من الحسابات غير المصنّفة';

    public function handle(): int
    {
        $map      = config('account_codes', []);
        $errors   = 0;
        $warnings = 0;
        $rows     = [];

        foreach ($map as $key => $meta) {
            $code = Setting::get($key, $meta['default'] ?? null);

            if (empty($code)) {
                $rows[] = [$key, '—', $meta['label'], '⚠️ غير مُهيّأ'];
                $warnings++;
                continue;
            }

            $acc = Account::where('code', $code)->first();
            if (!$acc) {
                $rows[] = [$key, $code, $meta['label'], '❌ غير موجود'];
                $errors++;
            } elseif (!$acc->is_active) {
                $rows[] = [$key, $code, $meta['label'], '❌ غير نشط'];
                $errors++;
            } elseif ($acc->is_header) {
                $rows[] = [$key, $code, $meta['label'], '❌ حساب رئيسي (تجميعي)'];
                $errors++;
            } else {
                $rows[] = [$key, $code, $meta['label'], '✅'];
            }
        }

        $this->table(['المفتاح', 'الكود', 'الحساب', 'الحالة'], $rows);

        // ── H-2: حسابات الميزانية بلا تصنيف فرعي صريح ──────────────────────────
        $unclassified = Account::where('is_active', true)
            ->where('is_header', false)
            ->whereIn('type', ['asset', 'liability', 'equity'])
            ->whereNull('sub_type')
            ->orderBy('code')
            ->get(['code', 'name', 'type']);

        if ($unclassified->isNotEmpty()) {
            $warnings += $unclassified->count();
            $this->newLine();
            $this->warn('⚠️ حسابات بلا تصنيف فرعي (sub_type) — ستُصنَّف في الميزانية حسب نطاق الكود (قد يكون خاطئاً):');
            $this->table(
                ['الكود', 'الاسم', 'النوع'],
                $unclassified->map(fn ($a) => [$a->code, $a->name, $a->type])->all()
            );
        }

        $this->newLine();
        if ($errors > 0) {
            $this->error("النتيجة: {$errors} خطأ، {$warnings} تحذير — صحّح دليل الحسابات/الإعدادات قبل الاعتماد للترحيل.");
            return self::FAILURE;
        }

        $this->info("النتيجة: لا أخطاء، {$warnings} تحذير.");
        return self::SUCCESS;
    }
}
