<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')
                  ->constrained('sales_quotations')->onDelete('cascade');
            $table->foreignId('product_id')
                  ->constrained('products')->onDelete('cascade');
            $table->decimal('quantity',         12, 3);
            $table->decimal('unit_price',       10, 2);
            $table->decimal('discount_percent',  5, 2)->default(0);
            $table->decimal('total_price',      14, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['quotation_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_quotation_items');
    }
};
