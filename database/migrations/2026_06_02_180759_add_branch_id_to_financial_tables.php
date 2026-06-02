<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds branch_id to all financial transaction tables.
 *
 * Architecture decision:
 *   journal_entries.branch_id  = core filter for ALL financial reports
 *   sales/purchases.branch_id  = denormalized for fast transaction reports
 *   vouchers/payments.branch_id = for voucher-level filtering
 *
 * Backfill strategy:
 *   - If record has warehouse_id → derive branch from warehouses.branch_id
 *   - Otherwise → use the system default branch (from settings)
 */
return new class extends Migration
{
    private array $tables = [
        'journal_entries',
        'sales',
        'purchases',
        'sale_returns',
        'purchase_returns',
        'receipt_vouchers',
        'payment_vouchers',
        'customer_payments',
        'supplier_payments',
        'employee_loans',
        'payroll_runs',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'branch_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->foreignId('branch_id')
                      ->nullable()
                      ->after('id')
                      ->constrained('branches')
                      ->nullOnDelete();
                });
            }
        }

        // ── Backfill branch_id ───────────────────────────────────────────────
        $defaultBranchId = (int) DB::table('settings')->where('key', 'default_branch_id')->value('value');

        // For tables that have warehouse_id: derive branch from warehouse
        $tablesWithWarehouse = ['sales', 'purchases', 'sale_returns', 'purchase_returns'];
        foreach ($tablesWithWarehouse as $table) {
            if (Schema::hasTable($table)) {
                // Update where warehouse has a branch
                DB::statement("
                    UPDATE {$table} t
                    INNER JOIN warehouses w ON w.id = t.warehouse_id
                    SET t.branch_id = w.branch_id
                    WHERE t.branch_id IS NULL AND w.branch_id IS NOT NULL
                ");
                // Fallback: use default branch
                if ($defaultBranchId) {
                    DB::table($table)
                        ->whereNull('branch_id')
                        ->update(['branch_id' => $defaultBranchId]);
                }
            }
        }

        // For journal_entries: derive from source transaction's branch_id
        // First pass: from sales
        DB::statement("
            UPDATE journal_entries je
            INNER JOIN sales s ON s.id = je.source_id
            SET je.branch_id = s.branch_id
            WHERE je.source_type LIKE '%Sale%'
              AND je.branch_id IS NULL
              AND s.branch_id IS NOT NULL
        ");

        // Second pass: from purchases
        DB::statement("
            UPDATE journal_entries je
            INNER JOIN purchases p ON p.id = je.source_id
            SET je.branch_id = p.branch_id
            WHERE je.source_type LIKE '%Purchase%'
              AND je.branch_id IS NULL
              AND p.branch_id IS NOT NULL
        ");

        // Third pass: remaining JEs → default branch
        if ($defaultBranchId) {
            DB::table('journal_entries')
                ->whereNull('branch_id')
                ->update(['branch_id' => $defaultBranchId]);
        }

        // For vouchers/payments: default branch
        $simpleDefaultTables = [
            'receipt_vouchers', 'payment_vouchers',
            'customer_payments', 'supplier_payments',
            'employee_loans', 'payroll_runs',
        ];
        foreach ($simpleDefaultTables as $table) {
            if (Schema::hasTable($table) && $defaultBranchId) {
                DB::table($table)
                    ->whereNull('branch_id')
                    ->update(['branch_id' => $defaultBranchId]);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'branch_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropForeign(['branch_id']);
                    $t->dropColumn('branch_id');
                });
            }
        }
    }
};
