<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expense Invoices — فواتير المصروفات
 *
 * Used for services/expenses received from vendors (rent, utilities, maintenance…)
 * that are NOT inventory purchases.
 *
 * GL on create:  DR expense_account_id  /  CR AP (account_ap_code)
 * GL on payment: DR AP                  /  CR cash / bank
 *
 * Differs from purchase_invoices (which DR inventory).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();         // EXP-YYYYMMdd-0001
            $table->string('vendor_name');                      // free-text vendor name
            $table->foreignId('supplier_id')                   // optional: link to suppliers table
                  ->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('expense_account_id')            // which expense account to DR
                  ->constrained('accounts')->onDelete('restrict');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();               // payment due date
            $table->string('vendor_invoice_number')->nullable();// vendor's own ref number
            $table->decimal('total_amount', 14, 2);
            $table->decimal('paid_amount',  14, 2)->default(0);
            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid');
            $table->text('notes')->nullable();
            $table->boolean('is_posted')->default(false);
            $table->timestamps();

            $table->index(['payment_status', 'due_date']);
            $table->index(['branch_id', 'invoice_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_invoices');
    }
};
