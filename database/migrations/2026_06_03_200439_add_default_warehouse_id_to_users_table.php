<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The "home warehouse" for this user — used as the default when creating
            // purchase receipts, inventory adjustments, stock counts, and transfers.
            // SAP equivalent: "default plant" in user master data (SU01).
            $table->foreignId('default_warehouse_id')
                  ->nullable()
                  ->after('pos_terminal_id')
                  ->constrained('warehouses')
                  ->nullOnDelete();
        });

        // Backfill: if user already has a pos_terminal, inherit its warehouse.
        \Illuminate\Support\Facades\DB::statement('
            UPDATE users u
            JOIN pos_terminals pt ON pt.id = u.pos_terminal_id
            SET u.default_warehouse_id = pt.warehouse_id
            WHERE u.pos_terminal_id IS NOT NULL
              AND u.default_warehouse_id IS NULL
              AND pt.warehouse_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['default_warehouse_id']);
            $table->dropColumn('default_warehouse_id');
        });
    }
};
