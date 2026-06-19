<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تسوية فرق الوردية (Cash Over & Short settlement).
 *
 * بعد إقفال الوردية يُثبَّت الفرق في حساب العجز (6530) أو الفائض (4160).
 * هذه الأعمدة تتتبّع كيف عُولِج الفرق لاحقًا (إعادة تصنيف لا تمسّ الصندوق):
 *   - charge_cashier     : تحميل العجز على الكاشير (ذمة موظف)
 *   - accept_expense     : اعتماد العجز كمصروف على المحل (لا قيد)
 *   - customer_liability : إثبات الفائض كأمانة عميل (التزام)
 *   - accept_income      : اعتماد الفائض كإيراد للمحل (لا قيد)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_shifts', function (Blueprint $t) {
            $t->boolean('variance_settled')->default(false)->after('variance_reason');
            $t->string('variance_settlement_type', 30)->nullable()->after('variance_settled');
            $t->foreignId('variance_settlement_entry_id')->nullable()->after('variance_settlement_type')
              ->constrained('journal_entries')->nullOnDelete();
            $t->timestamp('variance_settled_at')->nullable()->after('variance_settlement_entry_id');
            $t->foreignId('variance_settled_by')->nullable()->after('variance_settled_at')
              ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cash_shifts', function (Blueprint $t) {
            $t->dropConstrainedForeignId('variance_settlement_entry_id');
            $t->dropConstrainedForeignId('variance_settled_by');
            $t->dropColumn(['variance_settled', 'variance_settlement_type', 'variance_settled_at']);
        });
    }
};
