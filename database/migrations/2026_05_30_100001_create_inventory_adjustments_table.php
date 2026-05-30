<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_before', 10, 3);
            $table->decimal('quantity_after',  10, 3);
            $table->decimal('quantity_change',  10, 3); // after - before (negative = shrinkage)
            $table->decimal('cost_per_unit',   12, 2)->default(0);
            $table->decimal('total_cost',      12, 2)->default(0); // abs(change) * cost_per_unit
            $table->enum('reason', [
                'shrinkage',        // تلف / هالك
                'theft',            // سرقة
                'damage',           // ضرر / كسر
                'expiry',           // انتهاء صلاحية
                'count_correction', // تصحيح جرد
                'return_supplier',  // مرتجع مورد
                'other',            // أخرى
            ])->default('count_correction');
            $table->text('notes')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->nullOnDelete()->constrained('journal_entries');
            $table->foreignId('inventory_session_id')->nullable()->nullOnDelete()->constrained('inventory_sessions');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('product_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustments');
    }
};
