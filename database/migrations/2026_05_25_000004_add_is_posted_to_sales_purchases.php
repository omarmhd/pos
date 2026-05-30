<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->boolean('is_posted')->default(false)->after('change_amount');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->boolean('is_posted')->default(false)->after('paid_amount');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('is_posted');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn('is_posted');
        });
    }
};
