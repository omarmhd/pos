<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 4160: POS Cash Overage (revenue — credit normal)
        // Previously shared code 4150 with "Gain on Disposal of Fixed Assets".
        DB::table('accounts')->insertOrIgnore([
            'code'       => '4160',
            'name'       => 'فائض نقدي - ورديات',
            'type'       => 'revenue',
            'sub_type'   => null,
            'is_header'  => false,
            'is_active'  => true,
            'parent_id'  => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 6530: POS Cash Shortage (expense — debit normal)
        // Previously shared code 6520 with "Loss on Disposal of Fixed Assets".
        DB::table('accounts')->insertOrIgnore([
            'code'       => '6530',
            'name'       => 'عجز نقدي - ورديات',
            'type'       => 'expense',
            'sub_type'   => null,
            'is_header'  => false,
            'is_active'  => true,
            'parent_id'  => DB::table('accounts')->where('code', '6000')->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Repoint settings only if still on the colliding defaults — preserves any
        // custom mapping the client may have already configured.
        DB::table('settings')
            ->where('key', 'account_pos_cash_overage_code')->where('value', '4150')
            ->update(['value' => '4160', 'updated_at' => now()]);

        DB::table('settings')
            ->where('key', 'account_pos_cash_shortage_code')->where('value', '6520')
            ->update(['value' => '6530', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('key', 'account_pos_cash_overage_code')->where('value', '4160')
            ->update(['value' => '4150', 'updated_at' => now()]);

        DB::table('settings')
            ->where('key', 'account_pos_cash_shortage_code')->where('value', '6530')
            ->update(['value' => '6520', 'updated_at' => now()]);

        DB::table('accounts')->whereIn('code', ['4160', '6530'])->delete();
    }
};
