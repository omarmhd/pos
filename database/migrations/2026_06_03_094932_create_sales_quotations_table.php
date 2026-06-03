<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sales Quotations — عروض الأسعار
 *
 * A quotation is sent to the customer before a sale is confirmed.
 * No GL entry is created — only Sales Orders and Invoices create GL.
 *
 * Lifecycle: draft → sent → accepted | rejected | expired
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_number')->unique();           // QT-YYYYMMdd-0001
            $table->foreignId('customer_id')->nullable()
                  ->constrained('customers')->nullOnDelete();
            $table->string('customer_name')->nullable();            // ad-hoc customer name
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()
                  ->constrained('branches')->nullOnDelete();
            $table->foreignId('price_list_id')->nullable()
                  ->constrained('price_lists')->nullOnDelete();

            $table->date('quotation_date');
            $table->date('valid_until')->nullable();                // expiry date

            $table->enum('status', ['draft','sent','accepted','rejected','expired'])
                  ->default('draft');

            $table->decimal('subtotal',       14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('total_amount',   14, 2)->default(0);

            $table->text('terms')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'customer_id']);
            $table->index(['branch_id', 'quotation_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_quotations');
    }
};
