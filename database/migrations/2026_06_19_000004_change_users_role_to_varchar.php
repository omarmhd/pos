<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * توسعة عمود users.role من ENUM محدود إلى VARCHAR.
 *
 * كان: enum('admin','manager','cashier') — فيرفض أدوارًا مثل accountant / branch_manager
 * (خطأ 1265 Data truncated). الأدوار تُدار عبر Spatie، وهذا العمود قيمة مرافقة (legacy)
 * يجب أن يقبل أي اسم دور.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `users` MODIFY `role` VARCHAR(50) NOT NULL DEFAULT 'cashier'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `users` MODIFY `role` ENUM('admin','manager','cashier') NOT NULL DEFAULT 'cashier'");
    }
};
