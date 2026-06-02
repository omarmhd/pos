<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_number')->unique();
            $table->date('voucher_date');
            $table->string('received_from');                               // من المستلم منه (نص حر)
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('account_id')->constrained('accounts');      // الحساب الدائن (ما استُلم مقابله)
            $table->foreignId('cash_account_id')->constrained('accounts'); // الحساب المدين (صندوق/بنك)
            $table->decimal('amount', 14, 2);
            $table->string('payment_method')->default('cash');             // cash | bank | mobile_wallet
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained();
            $table->boolean('is_posted')->default(false);
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payment_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_number')->unique();
            $table->date('voucher_date');
            $table->string('paid_to');                                     // لمن دُفع (نص حر)
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('account_id')->constrained('accounts');      // الحساب المدين (ما دُفع مقابله)
            $table->foreignId('cash_account_id')->constrained('accounts'); // الحساب الدائن (صندوق/بنك)
            $table->decimal('amount', 14, 2);
            $table->string('payment_method')->default('cash');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained();
            $table->boolean('is_posted')->default(false);
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_vouchers');
        Schema::dropIfExists('receipt_vouchers');
    }
};
