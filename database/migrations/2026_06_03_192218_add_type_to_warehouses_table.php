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
        Schema::table('warehouses', function (Blueprint $table) {
            // main = back-store / primary storage
            // floor = shop floor / display shelves (feeds POS)
            // returns = returns / quarantine area
            // transit = in-transit (inter-branch transfers)
            $table->enum('type', ['main', 'floor', 'returns', 'transit'])
                  ->default('main')
                  ->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
