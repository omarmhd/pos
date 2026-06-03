<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POS Terminals — نقاط البيع
 *
 * Each terminal is assigned to a specific warehouse.
 * In a mall: cashier at display floor → linked to WH-FLOOR (معرض)
 * In a wholesale store: cashier at backroom → linked to WH-MAIN
 *
 * This solves: "Does POS deduct from the warehouse or the showroom?"
 * Answer: from the warehouse assigned to THIS terminal (usually the floor).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_terminals', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();              // POS-01, KIOSK-02
            $table->string('name', 100);                       // كاشير 1 — معرض الطابق الأرضي
            $table->foreignId('branch_id')
                  ->constrained('branches')->onDelete('cascade');
            $table->foreignId('warehouse_id')                  // ← WHERE it deducts stock from
                  ->constrained('warehouses')->onDelete('restrict');
            $table->foreignId('price_list_id')->nullable()     // optional: override pricing
                  ->constrained('price_lists')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_terminals');
    }
};
