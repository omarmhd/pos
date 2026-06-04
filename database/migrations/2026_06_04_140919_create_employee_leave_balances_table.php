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
        Schema::create('employee_leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->enum('leave_type', ['annual', 'sick', 'unpaid', 'maternity', 'emergency'])
                  ->default('annual');
            $table->unsignedSmallInteger('entitled_days')->default(21);  // annual entitlement
            $table->decimal('used_days',    5, 1)->default(0);
            $table->decimal('balance_days', 5, 1)->default(0);  // entitled - used
            $table->timestamps();

            $table->unique(['employee_id', 'year', 'leave_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_leave_balances');
    }
};
