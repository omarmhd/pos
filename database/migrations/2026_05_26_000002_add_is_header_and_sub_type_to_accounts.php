<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            // Marks summary/parent accounts that cannot receive direct journal entry lines
            $table->boolean('is_header')->default(false)->after('is_active');
            // Explicit classification for reliable Balance Sheet bucketing
            $table->string('sub_type', 30)->nullable()->after('is_header');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['is_header', 'sub_type']);
        });
    }
};
