<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code', 50)->unique();      // FA-0001
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('category_id')
                  ->constrained('fixed_asset_categories')->onDelete('restrict');
            $table->foreignId('branch_id')->nullable()
                  ->constrained('branches')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            // GL override — inherits from category if null
            $table->foreignId('journal_entry_id')->nullable()
                  ->constrained('journal_entries')->nullOnDelete();

            // Purchase info
            $table->date('purchase_date');
            $table->decimal('purchase_cost',   14, 2);
            $table->decimal('residual_value',  14, 2)->default(0); // salvage value
            $table->string('supplier_name')->nullable();

            // Depreciation config
            $table->enum('depreciation_method', ['straight_line', 'declining_balance'])
                  ->default('straight_line');
            $table->unsignedSmallInteger('useful_life_months');
            // For declining balance: annual rate (e.g., 0.20 = 20%)
            $table->decimal('depreciation_rate', 6, 4)->nullable();

            // Running totals (updated each time depreciation runs)
            $table->decimal('accumulated_depreciation', 14, 2)->default(0);
            $table->decimal('net_book_value',           14, 2)->default(0);

            // Status lifecycle
            $table->enum('status', ['active', 'fully_depreciated', 'disposed'])
                  ->default('active');
            $table->date('disposal_date')->nullable();
            $table->decimal('disposal_amount', 14, 2)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->index(['status', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
