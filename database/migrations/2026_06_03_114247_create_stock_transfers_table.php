<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock Transfers — تحويلات المخزون الداخلية
 *
 * Used for moving stock between warehouses within the same branch.
 * Most common use case: Backroom → Floor (إعادة تعبئة الرفوف)
 *
 * NO GL journal entry is created — this is a PHYSICAL movement only.
 * Total inventory value (Account 1300) remains unchanged.
 * Only stock_levels per warehouse change.
 *
 * Audit trail: StockMovements created (transfer_out + transfer_in)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number')->unique();        // TRF-YYYYMMdd-0001
            $table->foreignId('from_warehouse_id')
                  ->constrained('warehouses')->onDelete('restrict');
            $table->foreignId('to_warehouse_id')
                  ->constrained('warehouses')->onDelete('restrict');
            $table->foreignId('branch_id')->nullable()
                  ->constrained('branches')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('transfer_date');
            $table->enum('status', ['draft', 'completed', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'transfer_date']);
            $table->index(['status', 'from_warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
