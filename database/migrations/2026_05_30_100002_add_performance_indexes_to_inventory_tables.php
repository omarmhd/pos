<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_sessions', function (Blueprint $table) {
            $table->index('status');
            $table->index('created_at');
            $table->index(['status', 'created_at']);
        });

        Schema::table('inventory_session_items', function (Blueprint $table) {
            $table->index('product_id');
            $table->index('counted_quantity');
            $table->index(['session_id', 'difference']);
        });

        Schema::table('inventory_adjustments', function (Blueprint $table) {
            $table->index('inventory_session_id');
            $table->index('journal_entry_id');
            $table->index('reason');
            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_sessions', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['status', 'created_at']);
        });
        Schema::table('inventory_session_items', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
            $table->dropIndex(['counted_quantity']);
            $table->dropIndex(['session_id', 'difference']);
        });
        Schema::table('inventory_adjustments', function (Blueprint $table) {
            $table->dropIndex(['inventory_session_id']);
            $table->dropIndex(['journal_entry_id']);
            $table->dropIndex(['reason']);
            $table->dropIndex(['product_id', 'created_at']);
        });
    }
};
