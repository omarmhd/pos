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
        Schema::table('customer_deposits', function (Blueprint $table) {
            $table->foreignId('branch_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('branches')
                  ->nullOnDelete();
        });

        // Backfill: assign each deposit to the branch of its creator.
        // If the user has no branch, leave null (single-branch / HQ deposit).
        \Illuminate\Support\Facades\DB::statement('
            UPDATE customer_deposits cd
            JOIN users u ON u.id = cd.user_id
            SET cd.branch_id = u.branch_id
            WHERE u.branch_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        Schema::table('customer_deposits', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });
    }
};
