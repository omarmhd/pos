<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sales Orders — أوامر البيع
 *
 * Confirmed customer orders (from quotation or directly).
 * No GL entry — only the resulting Sale (invoice) creates GL.
 *
 * Lifecycle: draft → confirmed → partial | fulfilled | cancelled
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();               // SO-YYYYMMdd-0001
            $table->foreignId('quotation_id')->nullable()
                  ->constrained('sales_quotations')->nullOnDelete();
            $table->foreignId('customer_id')
                  ->constrained('customers')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()
                  ->constrained('branches')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()
                  ->constrained('warehouses')->nullOnDelete();
            $table->foreignId('price_list_id')->nullable()
                  ->constrained('price_lists')->nullOnDelete();

            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();

            $table->enum('status', ['draft','confirmed','partial','fulfilled','cancelled'])
                  ->default('draft');
            $table->boolean('is_credit')->default(false);           // credit or cash sale

            $table->decimal('total_amount',   14, 2)->default(0);
            $table->text('terms')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'customer_id']);
            $table->index(['branch_id', 'order_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
    }
};
