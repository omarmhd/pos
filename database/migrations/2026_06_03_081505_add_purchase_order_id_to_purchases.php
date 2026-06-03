<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            // Add column only if it doesn't already exist (idempotent)
            if (!Schema::hasColumn('purchases', 'purchase_order_id')) {
                $table->foreignId('purchase_order_id')
                      ->nullable()
                      ->after('id')
                      ->constrained('purchase_orders')
                      ->nullOnDelete();
            } else {
                // Column exists (from failed previous attempt) — just add the FK
                $table->foreign('purchase_order_id')
                      ->references('id')
                      ->on('purchase_orders')
                      ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            if (Schema::hasColumn('purchases', 'purchase_order_id')) {
                $table->dropForeign(['purchase_order_id']);
                $table->dropColumn('purchase_order_id');
            }
        });
    }
};
