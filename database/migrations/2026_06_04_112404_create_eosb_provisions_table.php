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
        Schema::create('eosb_provisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');  // 1–12

            // Snapshot at posting time
            $table->decimal('base_salary',        12, 2);   // employee's salary this month
            $table->decimal('service_months',     10, 2);   // months of service at period end
            $table->decimal('service_years',      10, 4);   // = service_months / 12
            $table->decimal('provision_amount',   12, 2);   // this month's accrual
            $table->decimal('cumulative_amount',  12, 2);   // running total per employee

            $table->enum('status', ['posted', 'reversed'])->default('posted');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'period_year', 'period_month']);
            $table->index(['period_year', 'period_month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eosb_provisions');
    }
};
