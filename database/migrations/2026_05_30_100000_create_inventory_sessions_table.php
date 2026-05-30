<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();           // INV-202506-001
            $table->string('title');
            $table->enum('status', ['draft','in_progress','approved','cancelled'])->default('draft');
            $table->unsignedBigInteger('category_id')->nullable(); // optional filter by category
            $table->text('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->nullOnDelete()->constrained('journal_entries');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('inventory_session_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('inventory_sessions')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('system_quantity', 10, 3);       // snapshot at session start
            $table->decimal('counted_quantity', 10, 3)->nullable();
            $table->decimal('difference', 10, 3)->default(0); // counted - system
            $table->decimal('cost_per_unit', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'product_id']);
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_session_items');
        Schema::dropIfExists('inventory_sessions');
    }
};
