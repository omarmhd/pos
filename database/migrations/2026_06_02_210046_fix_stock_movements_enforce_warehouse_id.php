<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Resolve system default warehouse
        $defaultWh = (int) DB::table('settings')->where('key', 'default_warehouse_id')->value('value');
        if (!$defaultWh) {
            $defaultWh = (int) DB::table('warehouses')->where('is_default', true)->value('id');
        }
        if (!$defaultWh) {
            $defaultWh = (int) DB::table('warehouses')->value('id');
        }
        if (!$defaultWh) {
            return; // No warehouses yet — skip
        }

        // ── Backfill from parent records ──────────────────────────────────────
        DB::statement("
            UPDATE stock_movements sm
            INNER JOIN sale_items si ON si.id = sm.reference_id
            INNER JOIN sales s ON s.id = si.sale_id
            SET sm.warehouse_id = s.warehouse_id
            WHERE sm.warehouse_id IS NULL
              AND sm.reference_type LIKE '%SaleItem%'
              AND s.warehouse_id IS NOT NULL
        ");

        DB::statement("
            UPDATE stock_movements sm
            INNER JOIN purchase_items pi ON pi.id = sm.reference_id
            INNER JOIN purchases p ON p.id = pi.purchase_id
            SET sm.warehouse_id = p.warehouse_id
            WHERE sm.warehouse_id IS NULL
              AND sm.reference_type LIKE '%PurchaseItem%'
              AND p.warehouse_id IS NOT NULL
        ");

        DB::statement("
            UPDATE stock_movements sm
            INNER JOIN sale_return_items sri ON sri.id = sm.reference_id
            INNER JOIN sale_returns sr ON sr.id = sri.sale_return_id
            SET sm.warehouse_id = sr.warehouse_id
            WHERE sm.warehouse_id IS NULL
              AND sm.reference_type LIKE '%SaleReturnItem%'
              AND sr.warehouse_id IS NOT NULL
        ");

        DB::statement("
            UPDATE stock_movements sm
            INNER JOIN purchase_return_items pri ON pri.id = sm.reference_id
            INNER JOIN purchase_returns pr ON pr.id = pri.purchase_return_id
            SET sm.warehouse_id = pr.warehouse_id
            WHERE sm.warehouse_id IS NULL
              AND sm.reference_type LIKE '%PurchaseReturnItem%'
              AND pr.warehouse_id IS NOT NULL
        ");

        DB::statement("
            UPDATE stock_movements sm
            INNER JOIN inventory_adjustments ia ON ia.id = sm.reference_id
            SET sm.warehouse_id = ia.warehouse_id
            WHERE sm.warehouse_id IS NULL
              AND sm.reference_type LIKE '%InventoryAdjustment%'
              AND ia.warehouse_id IS NOT NULL
        ");

        // Remaining NULLs → default warehouse
        DB::table('stock_movements')
            ->whereNull('warehouse_id')
            ->update(['warehouse_id' => $defaultWh]);

        // ── Enforce NOT NULL + add FK + index ──────────────────────────────────
        DB::statement('ALTER TABLE stock_movements MODIFY COLUMN warehouse_id BIGINT UNSIGNED NOT NULL');

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
            $table->index(['warehouse_id', 'product_id', 'created_at'], 'idx_sm_wh_prod_date');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropIndex('idx_sm_wh_prod_date');
        });
        DB::statement('ALTER TABLE stock_movements MODIFY COLUMN warehouse_id BIGINT UNSIGNED NULL');
    }
};
