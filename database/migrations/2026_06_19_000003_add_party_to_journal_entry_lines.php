<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ربط سطر القيد بطرف (أستاذ مساعد) فوق حساب المراقبة.
 *
 * عند اختيار عميل/مورّد/موظف في القيد، يُرحَّل السطر على حساب المراقبة المناسب
 * (ذمم العملاء / الموردين / حساب الموظفين) ويُحفظ الطرف هنا، فتظهر الحركة
 * في كشف حساب الطرف. polymorphic: party_type = App\Models\Customer|Supplier|Employee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $t) {
            $t->string('party_type')->nullable()->after('account_id');
            $t->unsignedBigInteger('party_id')->nullable()->after('party_type');
            $t->index(['party_type', 'party_id']);
        });
    }

    public function down(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $t) {
            $t->dropIndex(['party_type', 'party_id']);
            $t->dropColumn(['party_type', 'party_id']);
        });
    }
};
