<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. حساب سلف الموظفين (1250) — أصل جاري
        DB::table('accounts')->insertOrIgnore([
            'code'       => '1250',
            'name'       => 'سلف الموظفين',
            'type'       => 'asset',
            'sub_type'   => 'current_asset',
            'is_header'  => false,
            'is_active'  => true,
            'notes'      => 'مبالغ مُسلَّفة للموظفين تُستقطع من رواتبهم',
            'parent_id'  => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. جدول سلف الموظفين
        Schema::create('employee_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('loan_date');
            $table->decimal('amount', 14, 2);                       // المبلغ الأصلي
            $table->decimal('remaining_balance', 14, 2);            // المتبقي للاستقطاع
            $table->decimal('monthly_installment', 14, 2);          // القسط الشهري
            $table->unsignedSmallInteger('installments_total');      // عدد الأقساط الكلي
            $table->unsignedSmallInteger('installments_paid')->default(0); // الأقساط المسددة
            $table->enum('status', ['active', 'settled', 'cancelled'])->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained();             // من أجاز السلفة
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        // 3. عمود خصم السلف في بنود الرواتب
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->decimal('loan_deduction', 14, 2)->default(0)->after('other_deductions');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn('loan_deduction');
        });
        Schema::dropIfExists('employee_loans');
        DB::table('accounts')->where('code', '1250')->delete();
    }
};
