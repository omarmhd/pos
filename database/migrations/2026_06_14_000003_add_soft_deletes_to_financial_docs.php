<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حذف ناعم (Soft Deletes) للمستندات المالية (تدقيق H-3).
 * يحفظ أثر التدقيق: لا يُفقد المستند فعليًا عند الحذف، والمستندات المُرحَّلة تُمنع من الحذف.
 */
return new class extends Migration
{
    private array $tables = [
        'checks',
        'customs_declarations',
        'service_invoices',
        'receipt_vouchers',
        'payment_vouchers',
        'expense_invoices',
    ];

    public function up(): void
    {
        foreach ($this->tables as $t) {
            if (Schema::hasTable($t) && !Schema::hasColumn($t, 'deleted_at')) {
                Schema::table($t, fn (Blueprint $table) => $table->softDeletes());
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $t) {
            if (Schema::hasTable($t) && Schema::hasColumn($t, 'deleted_at')) {
                Schema::table($t, fn (Blueprint $table) => $table->dropSoftDeletes());
            }
        }
    }
};
