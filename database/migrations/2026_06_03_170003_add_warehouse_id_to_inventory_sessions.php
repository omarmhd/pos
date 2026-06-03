<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add warehouse_id to inventory_sessions.
 *
 * IAS 2 requires physical counts per LOCATION.
 * Each session now represents a count of ONE specific warehouse.
 * system_quantity will be sourced from stock_levels[warehouse_id]
 * rather than the global products.quantity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_sessions', function (Blueprint $table) {
            $table->foreignId('warehouse_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('warehouses')
                  ->nullOnDelete();
        });

        // Backfill existing sessions with the default warehouse
        $defaultWh = (int) DB::table('settings')
            ->where('key', 'default_warehouse_id')
            ->value('value');

        if ($defaultWh) {
            DB::table('inventory_sessions')
                ->whereNull('warehouse_id')
                ->update(['warehouse_id' => $defaultWh]);
        }
    }

    public function down(): void
    {
        Schema::table('inventory_sessions', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });
    }
};
