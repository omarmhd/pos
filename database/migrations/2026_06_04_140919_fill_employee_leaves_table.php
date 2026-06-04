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
        // employee_leaves table may already exist as an empty stub from a previous migration.
        // Use createIfNotExists pattern to avoid duplicate table error.
        if (!Schema::hasTable('employee_leaves')) {
            Schema::create('employee_leaves', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

                $table->enum('leave_type', ['annual', 'sick', 'unpaid', 'maternity', 'emergency'])
                      ->default('annual');
                $table->date('start_date');
                $table->date('end_date');
                $table->unsignedSmallInteger('working_days');

                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->dateTime('approved_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'start_date']);
                $table->index(['status']);
            });
        } else {
            // Table exists as empty stub — add all missing columns
            Schema::table('employee_leaves', function (Blueprint $table) {
                if (!Schema::hasColumn('employee_leaves', 'employee_id')) {
                    $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                }
                if (!Schema::hasColumn('employee_leaves', 'approved_by')) {
                    $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('employee_leaves', 'leave_type')) {
                    $table->enum('leave_type', ['annual', 'sick', 'unpaid', 'maternity', 'emergency'])
                          ->default('annual');
                }
                if (!Schema::hasColumn('employee_leaves', 'start_date')) {
                    $table->date('start_date');
                    $table->date('end_date');
                    $table->unsignedSmallInteger('working_days')->default(0);
                }
                if (!Schema::hasColumn('employee_leaves', 'status')) {
                    $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                    $table->dateTime('approved_at')->nullable();
                    $table->text('rejection_reason')->nullable();
                    $table->text('notes')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_leaves');
    }
};
