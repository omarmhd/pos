<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained('price_lists')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            // The specific price for this product in this price list.
            // NULL = "not set for this list → use products.selling_price as fallback"
            $table->decimal('selling_price', 10, 2);
            // Minimum quantity required to get this price (for tiered wholesale)
            $table->decimal('min_quantity', 12, 3)->default(1);
            $table->timestamps();

            $table->unique(['price_list_id', 'product_id']);
            $table->index(['product_id', 'price_list_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
