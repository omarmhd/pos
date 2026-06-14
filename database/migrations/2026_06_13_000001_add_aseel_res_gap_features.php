<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إغلاق فجوات كشف الإيرادات والمصروفات مقابل "الأصيل الذهبي" — بمسميات IFRS/IAS:
 *  1. ضريبة مدخلات الأصول الرأسمالية (Input VAT on Capital Assets — IAS 16/IAS 32)
 *     + ربط الأصول بآلية العضوية الحصرية للكشف.
 *  2. مستند الإقرار الجمركي (Customs Declaration / Import VAT) — لضريبة مدخلات الواردات.
 *  3. أعمدة لقطة جديدة على الكشف: الخدمات، الأصول، الجمارك، الإشعارات الدائنة/المدينة.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. ضريبة مدخلات الأصول الرأسمالية + عضوية الكشف ──────────────────
        Schema::table('fixed_assets', function (Blueprint $t) {
            $t->decimal('tax_amount', 14, 2)->default(0)->after('purchase_cost'); // ضريبة مدخلات الأصل
            $t->foreignId('res_statement_id')->nullable()
              ->constrained('revenue_expense_statements')->nullOnDelete();
            $t->index('res_statement_id');
        });

        // ── 2. مستند الإقرار الجمركي (Customs Declaration) ───────────────────
        Schema::create('customs_declarations', function (Blueprint $t) {
            $t->id();
            $t->string('declaration_number', 30)->unique();
            $t->date('declaration_date');
            $t->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $t->string('vendor_name')->nullable();           // المورد الأجنبي إن لم يكن مسجلاً
            $t->string('customs_ref')->nullable();           // رقم البيان الجمركي الرسمي
            $t->decimal('total_amount', 14, 2)->default(0);  // قيمة الواردات + الرسوم الجمركية (تكلفة)
            $t->decimal('tax_amount', 14, 2)->default(0);    // ضريبة القيمة المضافة على الواردات (مدخلات)
            $t->string('notes')->nullable();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $t->foreignId('res_statement_id')->nullable()
              ->constrained('revenue_expense_statements')->nullOnDelete();
            $t->index('res_statement_id');
            $t->timestamps();
        });

        // ── 3. أعمدة لقطة إضافية على الكشف ───────────────────────────────────
        Schema::table('revenue_expense_statements', function (Blueprint $t) {
            // إيرادات الخدمات (Service Revenue — مذكرة ضمن المبيعات، لا تُضاف مرتين)
            $t->decimal('services_amount', 14, 2)->default(0)->after('sales_returns_amount');
            $t->decimal('services_tax', 14, 2)->default(0)->after('services_amount');
            // الإشعارات الدائنة (Credit Notes — مذكرة ضمن مرتجعات المبيعات)
            $t->decimal('credit_notes_amount', 14, 2)->default(0)->after('services_tax');
            // الأصول الرأسمالية (Capital Assets + Input VAT)
            $t->decimal('assets_amount', 14, 2)->default(0)->after('purchase_returns_amount');
            $t->decimal('assets_tax', 14, 2)->default(0)->after('assets_amount');
            // الإقرارات الجمركية (Customs / Import VAT)
            $t->decimal('customs_amount', 14, 2)->default(0)->after('assets_tax');
            $t->decimal('customs_tax', 14, 2)->default(0)->after('customs_amount');
            // الإشعارات المدينة (Debit Notes — مذكرة ضمن مرتجعات المشتريات)
            $t->decimal('debit_notes_amount', 14, 2)->default(0)->after('customs_tax');
        });
    }

    public function down(): void
    {
        Schema::table('revenue_expense_statements', function (Blueprint $t) {
            $t->dropColumn([
                'services_amount', 'services_tax', 'credit_notes_amount',
                'assets_amount', 'assets_tax', 'customs_amount', 'customs_tax',
                'debit_notes_amount',
            ]);
        });

        Schema::dropIfExists('customs_declarations');

        Schema::table('fixed_assets', function (Blueprint $t) {
            $t->dropConstrainedForeignId('res_statement_id');
            $t->dropColumn('tax_amount');
        });
    }
};
