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
        Schema::table('stock_movements', function (Blueprint $table) {
            // Lot/Batch tracking per IAS 2 + food-safety traceability standards
            $table->string('lot_number', 100)->nullable()->after('notes');
            $table->date('expiry_date')->nullable()->after('lot_number');
            $table->index(['product_id', 'expiry_date']);   // fast expiry lookups
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'expiry_date']);
            $table->dropColumn(['lot_number', 'expiry_date']);
        });
    }
};
