<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make stock_movements a true append-only event log.
 *
 * New columns:
 *   reversal_of → FK to original movement (null on normal movements)
 *   is_reversal → quick flag to identify reversal entries
 *
 * A reversal entry has:
 *   movement_type = opposite of original (in↔out)
 *   quantity      = same absolute value as original
 *   reversal_of   = id of the original movement
 *   is_reversal   = true
 *
 * Net balance = SUM(quantity) where movement_type='in' - SUM(quantity) where movement_type='out'
 * OR more simply: treat 'out' as negative: SUM(signed_quantity)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            // Self-referential FK: which original movement does this reverse?
            $table->foreignId('reversal_of')
                  ->nullable()
                  ->after('notes')
                  ->constrained('stock_movements')
                  ->nullOnDelete();

            // Quick flag for filtering
            $table->boolean('is_reversal')->default(false)->after('reversal_of');

            $table->index(['product_id', 'warehouse_id', 'is_reversal'], 'idx_sm_prod_wh_rev');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['reversal_of']);
            $table->dropIndex('idx_sm_prod_wh_rev');
            $table->dropColumn(['reversal_of', 'is_reversal']);
        });
    }
};
