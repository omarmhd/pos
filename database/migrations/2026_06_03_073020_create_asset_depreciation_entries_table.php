<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_depreciation_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')
                  ->constrained('fixed_assets')->onDelete('cascade');
            $table->foreignId('journal_entry_id')->nullable()
                  ->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()
                  ->constrained('branches')->nullOnDelete();

            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');   // 1-12

            $table->decimal('depreciation_amount',    14, 2);
            $table->decimal('accumulated_before',     14, 2);
            $table->decimal('accumulated_after',      14, 2);
            $table->decimal('net_book_value_after',   14, 2);

            $table->text('notes')->nullable();
            $table->timestamps();

            // Each asset can only have one depreciation entry per month
            $table->unique(['fixed_asset_id', 'period_year', 'period_month'], 'uq_asset_period');
            $table->index(['period_year', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_depreciation_entries');
    }
};
