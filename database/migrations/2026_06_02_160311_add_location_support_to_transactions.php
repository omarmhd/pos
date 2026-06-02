<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Create default branch ─────────────────────────────────────────
        $branchId = DB::table('branches')->insertGetId([
            'code'       => 'HQ',
            'name'       => 'المقر الرئيسي',
            'type'       => 'head_office',
            'is_default' => true,
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ── 2. Create default warehouse under that branch ────────────────────
        $warehouseId = DB::table('warehouses')->insertGetId([
            'code'       => 'WH-01',
            'name'       => 'المخزن الرئيسي',
            'branch_id'  => $branchId,
            'is_default' => true,
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ── 3. Migrate existing product quantities → stock_levels ────────────
        // Safe: uses insertOrIgnore so re-running never duplicates
        $products = DB::table('products')
            ->select('id', 'quantity', 'min_quantity')
            ->get();

        foreach ($products as $p) {
            DB::table('stock_levels')->insertOrIgnore([
                'warehouse_id' => $warehouseId,
                'product_id'   => $p->id,
                'quantity'     => $p->quantity,
                'min_quantity' => $p->min_quantity,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }

        // ── 4. Save default IDs to settings ─────────────────────────────────
        DB::table('settings')->updateOrInsert(
            ['key' => 'default_branch_id'],
            ['value' => $branchId, 'created_at' => now(), 'updated_at' => now()]
        );
        DB::table('settings')->updateOrInsert(
            ['key' => 'default_warehouse_id'],
            ['value' => $warehouseId, 'created_at' => now(), 'updated_at' => now()]
        );

        // ── 5. Add nullable warehouse_id to transaction tables ───────────────
        // All columns are nullable to preserve existing data.
        // NULL = "used default warehouse at the time" (backward-compat).
        $tablesNeedWarehouse = [
            'purchases', 'sales', 'sale_returns', 'purchase_returns', 'inventory_adjustments',
        ];
        foreach ($tablesNeedWarehouse as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'warehouse_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->foreignId('warehouse_id')
                      ->nullable()
                      ->after('id')
                      ->constrained('warehouses')
                      ->nullOnDelete();
                });
            }
        }

        // ── 6. Add nullable branch_id to users ───────────────────────────────
        if (!Schema::hasColumn('users', 'branch_id')) {
            Schema::table('users', function (Blueprint $t) {
                $t->foreignId('branch_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('branches')
                  ->nullOnDelete();
            });
        }

        // ── 7. Backfill existing transactions with default warehouse ─────────
        // This ensures old records are consistently linked instead of null.
        foreach ($tablesNeedWarehouse as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)
                    ->whereNull('warehouse_id')
                    ->update(['warehouse_id' => $warehouseId]);
            }
        }
    }

    public function down(): void
    {
        $tablesWithWarehouse = [
            'purchases', 'sales', 'sale_returns', 'purchase_returns', 'inventory_adjustments',
        ];
        foreach ($tablesWithWarehouse as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'warehouse_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropForeign(['warehouse_id']);
                    $t->dropColumn('warehouse_id');
                });
            }
        }

        if (Schema::hasColumn('users', 'branch_id')) {
            Schema::table('users', function (Blueprint $t) {
                $t->dropForeign(['branch_id']);
                $t->dropColumn('branch_id');
            });
        }

        DB::table('settings')->whereIn('key', ['default_branch_id', 'default_warehouse_id'])->delete();
    }
};
