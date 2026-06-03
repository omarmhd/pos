<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')
                  ->constrained('purchase_orders')->onDelete('cascade');
            $table->foreignId('product_id')
                  ->constrained('products')->onDelete('cascade');

            $table->decimal('quantity_ordered',  12, 3);
            $table->decimal('quantity_received', 12, 3)->default(0); // tracks partial receipt
            $table->decimal('unit_price',        10, 2);
            $table->decimal('total_price',       14, 2);

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['purchase_order_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
