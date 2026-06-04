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
        Schema::create('inter_branch_transfers', function (Blueprint $table) {
            $table->id();

            // The branch sending the funds
            $table->foreignId('from_branch_id')->constrained('branches')->cascadeOnDelete();
            // The branch receiving the funds
            $table->foreignId('to_branch_id')->constrained('branches')->cascadeOnDelete();

            $table->decimal('amount', 14, 2);
            $table->date('transfer_date');
            $table->string('reference')->nullable();
            $table->text('description')->nullable();

            // Two GL entries — one per branch
            $table->foreignId('from_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('to_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['from_branch_id', 'transfer_date']);
            $table->index(['to_branch_id',   'transfer_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inter_branch_transfers');
    }
};
