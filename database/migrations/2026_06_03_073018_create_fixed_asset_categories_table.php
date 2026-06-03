<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            // GL account mappings — each category has default accounts
            $table->foreignId('asset_account_id')
                  ->constrained('accounts')->onDelete('restrict');
            $table->foreignId('accumulated_dep_account_id')
                  ->constrained('accounts')->onDelete('restrict');
            $table->foreignId('depreciation_expense_account_id')
                  ->constrained('accounts')->onDelete('restrict');
            $table->enum('depreciation_method', ['straight_line', 'declining_balance'])
                  ->default('straight_line');
            $table->unsignedSmallInteger('useful_life_months')->default(60); // 5 years
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed default categories using existing chart of accounts
        $asset1510 = DB::table('accounts')->where('code', '1510')->value('id');
        $asset1520 = DB::table('accounts')->where('code', '1520')->value('id');
        $accDep    = DB::table('accounts')->where('code', '1600')->value('id');
        $depExp    = DB::table('accounts')->where('code', '6400')->value('id');

        if ($asset1510 && $accDep && $depExp) {
            DB::table('fixed_asset_categories')->insert([
                [
                    'code'                           => 'FURN',
                    'name'                           => 'أثاث ومعدات',
                    'asset_account_id'               => $asset1510,
                    'accumulated_dep_account_id'     => $accDep,
                    'depreciation_expense_account_id'=> $depExp,
                    'depreciation_method'            => 'straight_line',
                    'useful_life_months'             => 60,
                    'created_at'                     => now(),
                    'updated_at'                     => now(),
                ],
                [
                    'code'                           => 'COMP',
                    'name'                           => 'أجهزة حاسوب ومعدات تقنية',
                    'asset_account_id'               => $asset1520 ?? $asset1510,
                    'accumulated_dep_account_id'     => $accDep,
                    'depreciation_expense_account_id'=> $depExp,
                    'depreciation_method'            => 'straight_line',
                    'useful_life_months'             => 36,
                    'created_at'                     => now(),
                    'updated_at'                     => now(),
                ],
                [
                    'code'                           => 'EQUIP',
                    'name'                           => 'معدات وآلات',
                    'asset_account_id'               => $asset1510,
                    'accumulated_dep_account_id'     => $accDep,
                    'depreciation_expense_account_id'=> $depExp,
                    'depreciation_method'            => 'straight_line',
                    'useful_life_months'             => 84,
                    'created_at'                     => now(),
                    'updated_at'                     => now(),
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_categories');
    }
};
