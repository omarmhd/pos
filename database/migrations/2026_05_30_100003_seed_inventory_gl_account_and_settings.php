<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // Add account 6510 — مصروف عجز وخسائر المخزون (child of 6000)
        $parent = DB::table('accounts')->where('code', '6000')->value('id');
        DB::table('accounts')->updateOrInsert(
            ['code' => '6510'],
            [
                'name'       => 'مصروف عجز وخسائر المخزون',
                'type'       => 'expense',
                'sub_type'   => null,
                'is_header'  => false,
                'is_active'  => true,
                'parent_id'  => $parent,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        // Add GL mapping settings for inventory adjustments
        foreach ([
            'account_inventory_shortage_code' => '6510',
            'account_inventory_surplus_code'  => '4100',
        ] as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        DB::table('accounts')->where('code', '6510')->delete();
        DB::table('settings')->whereIn('key', [
            'account_inventory_shortage_code',
            'account_inventory_surplus_code',
        ])->delete();
    }
};
