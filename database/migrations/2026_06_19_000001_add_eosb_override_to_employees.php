<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تجاوز اختياري لقاعدة مكافأة نهاية الخدمة على مستوى الموظف.
 *
 * - eosb_tiers: شرائح خاصة بالموظف (JSON) مثل [{"to_year":5,"days_per_year":21},{"to_year":null,"days_per_year":30}]
 *   إن كانت فارغة (null) يُستخدم الإعداد العام.
 * - eosb_salary_base: أساس الراتب لهذا الموظف ('basic' أو 'gross') — null = اتّباع الإعداد العام.
 *
 * القاعدة العامة (الشرائح/الأساس/أيام الشهر) تُحفظ في جدول settings، لا هنا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $t) {
            $t->json('eosb_tiers')->nullable()->after('notes');
            $t->string('eosb_salary_base', 10)->nullable()->after('eosb_tiers');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $t) {
            $t->dropColumn(['eosb_tiers', 'eosb_salary_base']);
        });
    }
};
