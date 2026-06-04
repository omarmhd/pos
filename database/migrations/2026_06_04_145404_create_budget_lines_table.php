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
        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->unsignedTinyInteger('month')->nullable();  // null = annual line
            // 12 monthly amounts (Jan–Dec). Filled from a single annual figure
            // spread equally, or entered per month.
            $table->decimal('jan',0,0)->default(0); $table->decimal('feb',0,0)->default(0);
            $table->decimal('mar',0,0)->default(0); $table->decimal('apr',0,0)->default(0);
            $table->decimal('may',0,0)->default(0); $table->decimal('jun',0,0)->default(0);
            $table->decimal('jul',0,0)->default(0); $table->decimal('aug',0,0)->default(0);
            $table->decimal('sep',0,0)->default(0); $table->decimal('oct',0,0)->default(0);
            $table->decimal('nov',0,0)->default(0); $table->decimal('dec_',0,0)->default(0);
            $table->timestamps();
            $table->unique(['budget_id', 'account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budget_lines');
    }
};
