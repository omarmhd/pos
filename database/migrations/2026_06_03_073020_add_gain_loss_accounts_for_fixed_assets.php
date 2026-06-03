<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 4150: Gain on Disposal of Fixed Assets (revenue — credit normal)
        DB::table('accounts')->insertOrIgnore([
            'code'       => '4150',
            'name'       => 'أرباح بيع أصول ثابتة',
            'type'       => 'revenue',
            'sub_type'   => null,
            'is_header'  => false,
            'is_active'  => true,
            'parent_id'  => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 6520: Loss on Disposal of Fixed Assets (expense — debit normal)
        DB::table('accounts')->insertOrIgnore([
            'code'       => '6520',
            'name'       => 'خسائر بيع أصول ثابتة',
            'type'       => 'expense',
            'sub_type'   => null,
            'is_header'  => false,
            'is_active'  => true,
            'parent_id'  => DB::table('accounts')->where('code', '6000')->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Register in settings for easy override
        DB::table('settings')->updateOrInsert(
            ['key' => 'account_asset_gain_code'],
            ['value' => '4150', 'created_at' => now(), 'updated_at' => now()]
        );
        DB::table('settings')->updateOrInsert(
            ['key' => 'account_asset_loss_code'],
            ['value' => '6520', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        DB::table('accounts')->whereIn('code', ['4150', '6520'])->delete();
        DB::table('settings')->whereIn('key', ['account_asset_gain_code', 'account_asset_loss_code'])->delete();
    }
};
