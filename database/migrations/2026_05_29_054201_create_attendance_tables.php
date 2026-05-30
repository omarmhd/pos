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
        // ── جدول سجلات الحضور الخام ────────────────────────────────
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->dateTime('check_in')->nullable();
            $table->dateTime('check_out')->nullable();
            $table->string('source')->default('device'); // device, manual
            $table->timestamps();
            $table->index(['employee_id', 'check_in']);
        });

        // ── ملخص الحضور اليومي ────────────────────────────────
        Schema::create('attendance_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->date('attendance_date');
            $table->enum('status', ['present', 'absent', 'late', 'leave'])->default('absent');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->decimal('hours_worked', 8, 2)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'attendance_date']);
            $table->index(['attendance_date']);
        });

        // ── إجازات الموظفين ────────────────────────────────
        Schema::create('employee_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->date('leave_date');
            $table->enum('leave_type', ['sick', 'annual', 'unpaid', 'weekend'])->default('annual');
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'leave_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_leaves');
        Schema::dropIfExists('attendance_summaries');
        Schema::dropIfExists('attendance_logs');
    }
};
