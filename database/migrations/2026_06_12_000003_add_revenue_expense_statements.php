<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * كشف الإيرادات والمصروفات (مقارنة الأصيل الذهبي — فصل كشف الإيرادات والمصروفات):
 *  1. ضريبة المدخلات على المشتريات وفواتير المصروفات + حساب 1260
 *  2. مستند كشف دوري بعضوية حصرية للفواتير (كل فاتورة في كشف واحد فقط)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. ضريبة المدخلات ────────────────────────────────────────────────
        Schema::table('purchases', function (Blueprint $t) {
            $t->decimal('tax_amount', 12, 2)->default(0)->after('total_amount'); // ضريبة مدخلات
        });
        Schema::table('expense_invoices', function (Blueprint $t) {
            $t->decimal('tax_amount', 12, 2)->default(0)->after('total_amount');
        });

        // حساب "ضريبة قيمة مضافة — مدخلات" (أصل) + مفتاح الإعدادات
        if (!DB::table('accounts')->where('code', '1260')->exists()) {
            DB::table('accounts')->insert([
                'code'       => '1260',
                'name'       => 'ضريبة قيمة مضافة — مدخلات',
                'type'       => 'asset',
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        if (!DB::table('settings')->where('key', 'account_tax_input_code')->exists()) {
            DB::table('settings')->insert([
                'key' => 'account_tax_input_code', 'value' => '1260',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // ── 2. كشوف الإيرادات والمصروفات ─────────────────────────────────────
        Schema::create('revenue_expense_statements', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->date('statement_date');                    // تاريخ القطع
            $t->string('description')->nullable();         // بيان التقرير
            // لقطة الإجماليات (تُعاد تسويتها عند كل تعديل)
            $t->decimal('sales_amount', 14, 2)->default(0);
            $t->decimal('sales_tax', 14, 2)->default(0);
            $t->decimal('sales_returns_amount', 14, 2)->default(0);
            $t->decimal('purchases_amount', 14, 2)->default(0);
            $t->decimal('purchases_tax', 14, 2)->default(0);
            $t->decimal('purchase_returns_amount', 14, 2)->default(0);
            $t->decimal('expenses_amount', 14, 2)->default(0);
            $t->decimal('expenses_tax', 14, 2)->default(0);
            $t->decimal('net_amount', 14, 2)->default(0);      // المبلغ الإجمالي
            $t->decimal('net_vat', 14, 2)->default(0);         // الضريبة المضافة للدفع
            $t->decimal('profit_percent', 8, 2)->default(0);   // نسبة أرباح المتاجرة
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });

        // عضوية حصرية: كل مستند ينتمي لكشف واحد فقط
        foreach (['sales', 'sale_returns', 'purchases', 'purchase_returns', 'expense_invoices'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('res_statement_id')->nullable()
                  ->constrained('revenue_expense_statements')->nullOnDelete();
                $t->index('res_statement_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['sales', 'sale_returns', 'purchases', 'purchase_returns', 'expense_invoices'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('res_statement_id');
            });
        }
        Schema::dropIfExists('revenue_expense_statements');
        Schema::table('expense_invoices', fn(Blueprint $t) => $t->dropColumn('tax_amount'));
        Schema::table('purchases',        fn(Blueprint $t) => $t->dropColumn('tax_amount'));
        DB::table('settings')->where('key', 'account_tax_input_code')->delete();
    }
};
