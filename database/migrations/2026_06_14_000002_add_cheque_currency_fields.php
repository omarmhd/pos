<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * عملة الشيك وسعر الصرف (مطابق لحقول "عملة الشيك / السعر / المبلغ" في الأصيل).
 * المبلغ المُرحَّل (amount) يبقى بالعملة الأساسية؛ تُحفظ foreign_amount/exchange_rate
 * للمرجعية عند إدخال شيك بعملة أجنبية (لا تؤثر على القيد).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checks', function (Blueprint $t) {
            $t->foreignId('currency_id')->nullable()->after('amount')->constrained('currencies')->nullOnDelete();
            $t->decimal('exchange_rate', 16, 6)->default(1)->after('currency_id');
            $t->decimal('foreign_amount', 14, 2)->nullable()->after('exchange_rate');
        });
    }

    public function down(): void
    {
        Schema::table('checks', function (Blueprint $t) {
            $t->dropConstrainedForeignId('currency_id');
            $t->dropColumn(['exchange_rate', 'foreign_amount']);
        });
    }
};
