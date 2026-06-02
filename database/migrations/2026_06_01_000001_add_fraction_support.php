<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add allow_fractions flag to products
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('allow_fractions')->default(false)->after('unit')
                  ->comment('true = product sold by weight/volume (kg, L…)');
        });

        // 2. Widen quantity columns from INTEGER to DECIMAL(12,3)
        //    Using raw ALTER so it works without doctrine/dbal on MySQL/MariaDB
        DB::statement('ALTER TABLE products MODIFY COLUMN quantity DECIMAL(12,3) NOT NULL DEFAULT 0.000');
        DB::statement('ALTER TABLE sale_items MODIFY COLUMN quantity DECIMAL(12,3) NOT NULL DEFAULT 0.000');
        DB::statement('ALTER TABLE purchase_items MODIFY COLUMN quantity DECIMAL(12,3) NOT NULL DEFAULT 0.000');
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('allow_fractions');
        });

        DB::statement('ALTER TABLE products MODIFY COLUMN quantity INT NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE sale_items MODIFY COLUMN quantity INT NOT NULL');
        DB::statement('ALTER TABLE purchase_items MODIFY COLUMN quantity INT NOT NULL');
    }
};
