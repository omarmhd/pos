<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customer → price list: customer-specific pricing (highest priority)
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('price_list_id')
                  ->nullable()
                  ->after('credit_limit')
                  ->constrained('price_lists')
                  ->nullOnDelete();
        });

        // Branch → price list: branch default pricing (when no customer price list)
        Schema::table('branches', function (Blueprint $table) {
            $table->foreignId('price_list_id')
                  ->nullable()
                  ->after('is_active')
                  ->constrained('price_lists')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['price_list_id']);
            $table->dropColumn('price_list_id');
        });
        Schema::table('branches', function (Blueprint $table) {
            $table->dropForeign(['price_list_id']);
            $table->dropColumn('price_list_id');
        });
    }
};
