<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')
                  ->constrained('sales_orders')->onDelete('cascade');
            $table->foreignId('product_id')
                  ->constrained('products')->onDelete('cascade');
            $table->decimal('quantity_ordered',   12, 3);
            $table->decimal('quantity_delivered',  12, 3)->default(0);
            $table->decimal('unit_price',          10, 2);
            $table->decimal('total_price',         14, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['sales_order_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_items');
    }
};
