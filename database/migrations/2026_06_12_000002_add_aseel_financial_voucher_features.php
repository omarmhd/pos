<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ميزات المعاملات المالية (مقارنة الأصيل الذهبي — فصل المعاملات المالية):
 *  1. عملة وسعر صرف على سندات القبض والصرف (المحاسبة تبقى بالعملة الأساسية)
 *  2. خصم المصدر على سند القبض (نسبة + مبلغ) + حساب "خصم مصدر مدفوع مقدماً"
 *  3. تاريخ ثانٍ للسند
 *  4. الرقم الضريبي للعميل (مشتغل مرخص)
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['receipt_vouchers', 'payment_vouchers'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
                $t->decimal('exchange_rate', 14, 6)->default(1);   // سعر صرف العملة وقت السند
                $t->decimal('amount_fc', 14, 4)->nullable();        // المبلغ بالعملة الأجنبية
                $t->date('second_date')->nullable();                // تاريخ ثانٍ (كما في الأصيل)
            });
        }

        // خصم المصدر — على سندات القبض فقط
        Schema::table('receipt_vouchers', function (Blueprint $t) {
            $t->decimal('source_discount_rate', 5, 2)->default(0);    // النسبة %
            $t->decimal('source_discount_amount', 12, 2)->default(0); // المبلغ
        });

        // الرقم الضريبي للعميل (مشتغل مرخص)
        Schema::table('customers', function (Blueprint $t) {
            $t->string('tax_number', 50)->nullable()->after('email');
        });

        // ── حساب "خصم مصدر مدفوع مقدماً" (أصل متداول) + مفتاح الإعدادات ──────
        $exists = DB::table('accounts')->where('code', '1250')->exists();
        if (!$exists) {
            DB::table('accounts')->insert([
                'code'       => '1250',
                'name'       => 'خصم مصدر مدفوع مقدماً',
                'type'       => 'asset',
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (!DB::table('settings')->where('key', 'account_source_discount_code')->exists()) {
            DB::table('settings')->insert([
                'key'        => 'account_source_discount_code',
                'value'      => '1250',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $t) {
            $t->dropColumn('tax_number');
        });
        Schema::table('receipt_vouchers', function (Blueprint $t) {
            $t->dropColumn(['source_discount_rate', 'source_discount_amount']);
        });
        foreach (['receipt_vouchers', 'payment_vouchers'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('currency_id');
                $t->dropColumn(['exchange_rate', 'amount_fc', 'second_date']);
            });
        }
        DB::table('settings')->where('key', 'account_source_discount_code')->delete();
    }
};
