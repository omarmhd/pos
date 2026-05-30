<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reversals', function (Blueprint $table) {
            $table->id();
            $table->string('original_type');
            $table->unsignedBigInteger('original_id');
            $table->unsignedBigInteger('reversal_journal_entry_id')->nullable();
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reversals');
    }
};
