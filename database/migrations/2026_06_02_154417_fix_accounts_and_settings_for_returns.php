<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Add missing accounts ──────────────────────────────────────────
        // 4050: مردودات المشتريات (Purchase Returns) — contra-purchase, debit-normal
        DB::table('accounts')->insertOrIgnore([
            'code'       => '4050',
            'name'       => 'مردودات المشتريات',
            'type'       => 'expense',
            'sub_type'   => null,
            'is_header'  => false,
            'is_active'  => true,
            'notes'      => 'تُقيَّد بالدائن عند إرجاع بضاعة للمورد — تُخفَّض تكلفة المشتريات',
            'parent_id'  => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ── 2. Rename account 4100 to its correct purpose (Other Income) ─────
        // 4100 IS "إيرادات أخرى" in seeder — this is correct for inventory surplus.
        // Just make sure the name is explicit.
        DB::table('accounts')
            ->where('code', '4100')
            ->where('name', 'إيرادات أخرى')
            ->update(['name' => 'إيرادات أخرى / فوائض المخزون', 'updated_at' => now()]);

        // ── 3. Add/fix settings ───────────────────────────────────────────────
        $settings = [
            // Sales Returns should post to 4200 (مردودات المبيعات), NOT 4100
            'account_sales_returns_code'    => '4200',
            // Purchase Returns post to AP (deduction from supplier debt)
            // The DR side is AP (2000); CR side is Inventory (1300)
            // We store the AP code specifically for purchase returns
            'account_purchase_returns_code' => '2000',
            // Ensure these are set (may already exist from their own migrations)
            'account_employee_loans_code'   => '1250',
            'account_customer_deposits_code'=> '2050',
        ];

        foreach ($settings as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key'   => $key],
                ['value' => $value, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::table('accounts')->where('code', '4050')->delete();
        DB::table('settings')->whereIn('key', [
            'account_sales_returns_code',
            'account_purchase_returns_code',
        ])->delete();
    }
};
