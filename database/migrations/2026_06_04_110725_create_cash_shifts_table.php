<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cash_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('pos_terminal_id')->nullable()->constrained('pos_terminals')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->enum('status', ['open', 'closed'])->default('open');
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();

            // Financial counters
            $table->decimal('opening_amount',  12, 2)->default(0);   // cash placed in drawer at open
            $table->decimal('cash_sales',      12, 2)->default(0);   // computed at close: Σ cash sales
            $table->decimal('cash_returns',    12, 2)->default(0);   // computed at close: Σ cash refunds
            $table->decimal('expected_amount', 12, 2)->nullable();   // opening + sales − returns
            $table->decimal('closing_amount',  12, 2)->nullable();   // physical cash count at close
            $table->decimal('variance_amount', 12, 2)->nullable();   // closing − expected (+ = over, − = short)

            $table->text('variance_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['branch_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_shifts');
    }
};
