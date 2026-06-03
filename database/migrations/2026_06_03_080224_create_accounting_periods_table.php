<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Accounting Periods — إغلاق الفترات المحاسبية
 *
 * Each row represents one calendar month.
 * status = 'open'   → journal entries may be posted
 * status = 'locked' → NO new journal entries allowed for this period
 *
 * Default behaviour: periods are auto-opened on first use.
 * Only explicitly created rows (when locked) are stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');   // 1-12
            $table->enum('status', ['open', 'locked'])->default('open');
            $table->text('notes')->nullable();
            $table->foreignId('locked_by')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('unlocked_by')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month']);
            $table->index(['status', 'year', 'month']);
        });

        // Pre-create the current month as open so the table is visible in the UI
        $now = now();
        DB::table('accounting_periods')->insertOrIgnore([
            'year'       => $now->year,
            'month'      => $now->month,
            'status'     => 'open',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');
    }
};
