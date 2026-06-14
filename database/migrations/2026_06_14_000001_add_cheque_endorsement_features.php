<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إغلاق فجوات الشيكات مقابل "الأصيل الذهبي":
 *  1. تجيير الشيك (Endorsement) — تظهير شيك وارد لمورد لسداد ذمته (مسار "1" في الأصيل).
 *  2. فرع البنك (Bank branch) — حقل وصفي مطابق لحقل "الفرع" في الأصيل.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checks', function (Blueprint $t) {
            $t->string('bank_branch')->nullable()->after('bank_name'); // فرع البنك
            $t->foreignId('endorsed_to_supplier_id')->nullable()->after('supplier_id')
              ->constrained('suppliers')->nullOnDelete();              // المورد المُجيَّر له الشيك
        });
    }

    public function down(): void
    {
        Schema::table('checks', function (Blueprint $t) {
            $t->dropConstrainedForeignId('endorsed_to_supplier_id');
            $t->dropColumn('bank_branch');
        });
    }
};
