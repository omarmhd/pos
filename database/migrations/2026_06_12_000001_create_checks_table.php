<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checks', function (Blueprint $table) {
            $table->id();
            $table->string('check_number')->unique();          // رقم الشيك التلقائي (CHK-Ymd-0001)
            $table->string('check_ref')->nullable();           // رقم الشيك الفعلي على الورق
            $table->enum('type', ['receivable', 'payable']);  // وارد (من عميل) / صادر (لمورد)
            $table->date('check_date');                        // تاريخ الشيك
            $table->date('due_date');                          // تاريخ الاستحقاق
            $table->decimal('amount', 14, 2);
            $table->string('bank_name')->nullable();           // اسم البنك المسحوب عليه
            $table->string('account_number')->nullable();      // رقم الحساب البنكي

            // الطرف الآخر (عميل أو مورد)
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('party_name')->nullable();         // اسم حر في حالة عدم الربط

            // الحالة
            // receivable: received → deposited → cleared | bounced
            // payable:    pending  → cleared   | returned
            $table->enum('status', [
                'received',   // وارد - تحت التحصيل (receivable initial)
                'deposited',  // مودَع في البنك (receivable)
                'cleared',    // مُقاصّ / تم الصرف (receivable & payable)
                'bounced',    // مرتجع (receivable)
                'pending',    // بانتظار الصرف (payable initial)
                'returned',   // أُعيد (payable)
            ])->default('received');

            $table->text('notes')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained();

            // روابط القيود المحاسبية لكل مرحلة
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();         // قيد الاستلام/الإصدار
            $table->foreignId('deposit_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete(); // قيد الإيداع
            $table->foreignId('clearing_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete(); // قيد المقاصة/الارتداد

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checks');
    }
};
