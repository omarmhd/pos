<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إغلاق فجوات الفواتير مقابل "الأصيل الذهبي" — بمسميات IFRS:
 *  1. رقم التسجيل الضريبي (TRN) للمورد (العميل لديه tax_number مسبقاً).
 *  2. تحسينات فاتورة البيع: خصم نسبة، تجاوز "شامل الضريبة"، مرجع المقاصة (Set-off).
 *  3. الدفع المختلط + الشيكات على الفاتورة (نقد/شيك/آجل).
 *  4. مرجع المقاصة على فاتورة الشراء.
 *  5. مستند فاتورة إيراد الخدمات (Service Revenue Invoice — IFRS 15) + حساب الإيراد.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. TRN للمورد ────────────────────────────────────────────────────
        Schema::table('suppliers', function (Blueprint $t) {
            $t->string('tax_number')->nullable()->after('company'); // رقم التسجيل الضريبي
        });

        // ── 2 + 3. تحسينات فاتورة البيع + الدفع المختلط ───────────────────────
        Schema::table('sales', function (Blueprint $t) {
            $t->decimal('discount_percent', 5, 2)->default(0)->after('discount'); // خصم نسبة
            $t->boolean('tax_inclusive')->nullable()->after('tax');               // تجاوز إعداد "شامل الضريبة"
            $t->string('setoff_ref')->nullable()->after('change_amount');         // مرجع قيد المقاصة (IAS 32)
            // الدفع المختلط (نقد + شيك + آجل) — صفر = السلوك الحالي
            $t->decimal('cash_amount', 12, 2)->default(0)->after('paid_amount');
            $t->decimal('cheque_amount', 12, 2)->default(0)->after('cash_amount');
            $t->foreignId('cheque_id')->nullable()->after('cheque_amount')
              ->constrained('checks')->nullOnDelete();
        });

        // ── 4. مرجع المقاصة على فاتورة الشراء ────────────────────────────────
        Schema::table('purchases', function (Blueprint $t) {
            $t->string('setoff_ref')->nullable();
        });

        // ── 5. فاتورة إيراد الخدمات (IFRS 15) ────────────────────────────────
        // حساب إيراد خدمات (إيراد) — 4200
        if (!DB::table('accounts')->where('code', '4200')->exists()) {
            DB::table('accounts')->insert([
                'code' => '4200', 'name' => 'إيرادات الخدمات', 'type' => 'revenue',
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        if (!DB::table('settings')->where('key', 'account_service_revenue_code')->exists()) {
            DB::table('settings')->insert([
                'key' => 'account_service_revenue_code', 'value' => '4200',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Schema::create('service_invoices', function (Blueprint $t) {
            $t->id();
            $t->string('invoice_number', 30)->unique();
            $t->date('invoice_date');
            $t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $t->string('customer_name')->nullable();          // اسم حر إن لم يكن مسجلاً
            $t->foreignId('service_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $t->string('description')->nullable();
            $t->decimal('total_amount', 14, 2)->default(0);   // الإجمالي شامل الضريبة
            $t->decimal('tax_amount', 14, 2)->default(0);     // ضريبة مخرجات
            $t->boolean('is_credit')->default(false);         // آجل / نقدي
            $t->boolean('is_posted')->default(false);
            $t->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $t->foreignId('res_statement_id')->nullable()
              ->constrained('revenue_expense_statements')->nullOnDelete();
            $t->index('res_statement_id');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_invoices');
        DB::table('settings')->where('key', 'account_service_revenue_code')->delete();

        Schema::table('purchases', fn(Blueprint $t) => $t->dropColumn('setoff_ref'));

        Schema::table('sales', function (Blueprint $t) {
            $t->dropConstrainedForeignId('cheque_id');
            $t->dropColumn(['discount_percent', 'tax_inclusive', 'setoff_ref', 'cash_amount', 'cheque_amount']);
        });

        Schema::table('suppliers', fn(Blueprint $t) => $t->dropColumn('tax_number'));
    }
};
