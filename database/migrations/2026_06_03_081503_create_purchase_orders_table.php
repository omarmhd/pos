<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase Orders — أوامر الشراء
 *
 * A PO is a commitment document sent to a supplier.
 * It does NOT post to GL — only the resulting Purchase Invoice does.
 *
 * Lifecycle: draft → sent → partial | received | cancelled
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();            // PO-YYYYMMdd-0001
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();

            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->date('sent_at')->nullable();

            $table->enum('status', ['draft', 'sent', 'partial', 'received', 'cancelled'])
                  ->default('draft');

            $table->decimal('total_amount', 14, 2)->default(0);
            $table->text('terms')->nullable();               // payment / delivery terms
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->index(['status', 'supplier_id']);
            $table->index(['branch_id', 'order_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
