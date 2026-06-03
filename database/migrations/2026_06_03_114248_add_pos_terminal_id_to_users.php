<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Column may already exist from a previous failed attempt
        if (!Schema::hasColumn('users', 'pos_terminal_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('pos_terminal_id')
                      ->nullable()->after('branch_id')
                      ->constrained('pos_terminals')->nullOnDelete();
            });
        } else {
            // Add FK only
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('pos_terminal_id')
                      ->references('id')->on('pos_terminals')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'pos_terminal_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['pos_terminal_id']);
                $table->dropColumn('pos_terminal_id');
            });
        }
    }
};
