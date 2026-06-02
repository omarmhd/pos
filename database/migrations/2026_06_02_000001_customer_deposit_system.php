<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. حساب سُلَف العملاء (2050) — التزام جاري
        DB::table('accounts')->insertOrIgnore([
            'code'       => '2050',
            'name'       => 'سُلَف وودائع العملاء',
            'type'       => 'liability',
            'sub_type'   => 'current_liability',
            'is_header'  => false,
            'is_active'  => true,
            'notes'      => 'مبالغ مستلمة مقدماً من العملاء قبل تسليم البضاعة',
            'parent_id'  => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. جدول الإيداعات والاسترداد
        Schema::create('customer_deposits', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_number')->unique();
            $table->date('voucher_date');
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['deposit', 'refund']);   // إيداع أو استرداد
            $table->decimal('amount', 14, 2);
            $table->string('payment_method')->default('cash'); // cash | bank | mobile_wallet
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained();
            $table->boolean('is_posted')->default(false);
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        // 3. عمود balance_used في جدول المبيعات
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('balance_used', 14, 2)->default(0)->after('paid_amount');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('balance_used');
        });

        Schema::dropIfExists('customer_deposits');

        DB::table('accounts')->where('code', '2050')->delete();
    }
};
