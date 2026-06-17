<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * تهيئة آمنة لخرائط الحسابات (تدقيق 2026-06-17) — إضافية فقط، لا تُعدّل أي قيد مُرحَّل.
 *
 *  1) إنشاء حساب إيراد الخدمات المستقل 4400.
 *  2) منح الحساب المكرّر 1260 تصنيفاً فرعياً (حتى يُصنَّف صحيحاً ريثما تُرحَّل قيوده).
 *  3) توحيد مفاتيح الإعدادات على 1150 (ضريبة المدخلات) و4400 (إيراد الخدمات).
 *
 * أما تصحيح القيود التاريخية فيتم عبر قيود تسوية (Adjusting Entries) من الأمر:
 *   php artisan accounting:reclassify-historical            (مراجعة فقط — يطبع المبالغ)
 *   php artisan accounting:reclassify-historical --force    (ترحيل قيود التسوية فعلياً)
 * هذا يحافظ على أثر التدقيق الكامل (القيد الأصلي يبقى + قيد تصحيح جديد).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            // ── (1) حساب إيراد الخدمات 4400 ───────────────────────────────────
            if (!DB::table('accounts')->where('code', '4400')->exists()) {
                DB::table('accounts')->insert([
                    'code'       => '4400',
                    'name'       => 'إيرادات الخدمات',
                    'type'       => 'revenue',
                    'sub_type'   => null,
                    'is_header'  => false,
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // ── (2) تصنيف فرعي للحساب 1260 المكرّر (أصل متداول) ────────────────
            DB::table('accounts')->where('code', '1260')->whereNull('sub_type')
                ->update(['sub_type' => 'current_asset', 'updated_at' => now()]);

            // ── (3) توحيد مفاتيح الإعدادات ─────────────────────────────────────
            foreach ([
                'account_tax_input_code'       => '1150',
                'account_input_vat_code'       => '1150',
                'account_service_revenue_code' => '4400',
            ] as $key => $val) {
                DB::table('settings')->updateOrInsert(
                    ['key' => $key],
                    ['value' => $val, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        });
    }

    public function down(): void
    {
        // إضافي فقط — لا تراجع آلي لتجنّب حذف حساب قد يحمل قيوداً.
    }
};
