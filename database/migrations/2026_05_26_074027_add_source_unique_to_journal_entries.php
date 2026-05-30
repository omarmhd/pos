<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            // Prevent double-posting the same source transaction.
            // source_type + source_id must be unique when both are non-null.
            // Reversal entries share source_type/source_id with the original,
            // so the unique index only covers rows where reference starts with 'REV-'
            // — instead we use a partial approach: unique on (source_type, source_id, reference).
            // This allows one original + one reversal per transaction.
            $table->unique(['source_type', 'source_id', 'reference'], 'je_source_ref_unique');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropUnique('je_source_ref_unique');
        });
    }
};
