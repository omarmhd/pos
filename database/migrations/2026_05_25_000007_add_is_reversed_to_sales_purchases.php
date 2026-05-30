<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->boolean('is_reversed')->default(false)->after('is_posted');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->boolean('is_reversed')->default(false)->after('is_posted');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('is_reversed');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn('is_reversed');
        });
    }
};
