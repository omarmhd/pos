<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * فصل الأصناف عن المنتجات المصنّعة وفق IAS 2 §37.
 *  - product_class: merchandise / raw_material / manufactured / service
 *  - حسابات مخزون منفصلة: بضاعة (1300) / مواد خام (1310) / منتجات تامة (1320)
 * التصنيف محاسبي/عرضي؛ product_type يبقى يقود السلوك (تجميعي/خدمة).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. حقل الفئة ─────────────────────────────────────────────────────
        Schema::table('products', function (Blueprint $t) {
            if (!Schema::hasColumn('products', 'product_class')) {
                $t->string('product_class', 20)->nullable()->after('product_type')
                  ->comment('merchandise|raw_material|manufactured|service');
                $t->index('product_class');
            }
        });

        // ── 2. حسابات المخزون المنفصلة ───────────────────────────────────────
        $ensure = function (string $code, string $name) {
            if (!DB::table('accounts')->where('code', $code)->exists()) {
                DB::table('accounts')->insert([
                    'code' => $code, 'name' => $name, 'type' => 'asset',
                    'sub_type' => 'current_asset', 'is_active' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        };
        $ensure('1310', 'مخزون مواد خام');
        $ensure('1320', 'مخزون منتجات تامة الصنع');

        $setting = function (string $key, string $val) {
            if (!DB::table('settings')->where('key', $key)->exists()) {
                DB::table('settings')->insert([
                    'key' => $key, 'value' => $val,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        };
        $setting('account_inventory_raw_code', '1310');
        $setting('account_inventory_finished_code', '1320');

        // ── 3. تعبئة الفئة من النوع الحالي ───────────────────────────────────
        DB::table('products')->where('product_type', 'bundle')->update(['product_class' => 'manufactured']);
        DB::table('products')->where('product_type', 'service')->update(['product_class' => 'service']);
        DB::table('products')
            ->whereNull('product_class')
            ->update(['product_class' => 'merchandise']);
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', ['account_inventory_raw_code', 'account_inventory_finished_code'])->delete();
        Schema::table('products', function (Blueprint $t) {
            if (Schema::hasColumn('products', 'product_class')) {
                $t->dropColumn('product_class');
            }
        });
    }
};
