<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('inventory_account_id')->nullable()->after('quantity');
            $table->unsignedBigInteger('cogs_account_id')->nullable()->after('inventory_account_id');
            $table->foreign('inventory_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('cogs_account_id')->references('id')->on('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['inventory_account_id']);
            $table->dropForeign(['cogs_account_id']);
            $table->dropColumn(['inventory_account_id', 'cogs_account_id']);
        });
    }
};
