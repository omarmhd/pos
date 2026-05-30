<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // ── Chart of Accounts ────────────────────────────────────────────────
        // sub_type drives Balance Sheet classification (replaces fragile code-range logic)
        // is_header = true → summary account, blocks direct journal entry posting
        $accounts = [
            // ── Assets ───────────────────────────────────────────────────────
            // Current Assets
            ['code' => '1000', 'name' => 'الصندوق / النقدية',             'type' => 'asset',   'sub_type' => 'current_asset',       'is_header' => false],
            ['code' => '1100', 'name' => 'البنوك',                        'type' => 'asset',   'sub_type' => 'current_asset',       'is_header' => false],
            ['code' => '1150', 'name' => 'ضريبة القيمة المضافة المدخلات', 'type' => 'asset',   'sub_type' => 'current_asset',       'is_header' => false],
            ['code' => '1200', 'name' => 'ذمم العملاء',                   'type' => 'asset',   'sub_type' => 'current_asset',       'is_header' => false],
            ['code' => '1300', 'name' => 'المخزون',                       'type' => 'asset',   'sub_type' => 'current_asset',       'is_header' => false],
            ['code' => '1400', 'name' => 'مصروفات مدفوعة مقدماً',         'type' => 'asset',   'sub_type' => 'current_asset',       'is_header' => false],
            // Fixed Assets — 1500 is the header; 1510/1520 are its children
            ['code' => '1500', 'name' => 'الأصول الثابتة',                'type' => 'asset',   'sub_type' => 'fixed_asset',         'is_header' => true],
            ['code' => '1510', 'name' => 'أثاث ومعدات',                   'type' => 'asset',   'sub_type' => 'fixed_asset',         'is_header' => false],
            ['code' => '1520', 'name' => 'أجهزة حاسوب ومعدات',            'type' => 'asset',   'sub_type' => 'fixed_asset',         'is_header' => false],
            ['code' => '1600', 'name' => 'مجمع استهلاك الأصول الثابتة',   'type' => 'asset',   'sub_type' => 'fixed_asset',         'is_header' => false],
            // ── Liabilities ──────────────────────────────────────────────────
            // Current Liabilities
            ['code' => '2000', 'name' => 'ذمم الموردين',                  'type' => 'liability','sub_type' => 'current_liability',   'is_header' => false],
            ['code' => '2100', 'name' => 'التزامات أخرى / رواتب مستحقة', 'type' => 'liability','sub_type' => 'current_liability',   'is_header' => false],
            ['code' => '2200', 'name' => 'ضريبة القيمة المضافة المستحقة', 'type' => 'liability','sub_type' => 'current_liability',   'is_header' => false],
            ['code' => '2300', 'name' => 'مصروفات مستحقة الدفع',          'type' => 'liability','sub_type' => 'current_liability',   'is_header' => false],
            // Long-term Liabilities
            ['code' => '2500', 'name' => 'قروض طويلة الأجل',              'type' => 'liability','sub_type' => 'long_term_liability', 'is_header' => false],
            // ── Equity ───────────────────────────────────────────────────────
            ['code' => '3000', 'name' => 'رأس المال',                     'type' => 'equity',  'sub_type' => null,                  'is_header' => false],
            ['code' => '3100', 'name' => 'الأرباح المحتجزة',              'type' => 'equity',  'sub_type' => null,                  'is_header' => false],
            ['code' => '3200', 'name' => 'المسحوبات الشخصية',             'type' => 'equity',  'sub_type' => null,                  'is_header' => false],
            // ── Revenue ──────────────────────────────────────────────────────
            // 4200 and 4300 are contra-revenue (debit-normal), typed 'revenue' so they close correctly
            ['code' => '4000', 'name' => 'إيرادات المبيعات',              'type' => 'revenue', 'sub_type' => null,                  'is_header' => false],
            ['code' => '4100', 'name' => 'إيرادات أخرى',                  'type' => 'revenue', 'sub_type' => null,                  'is_header' => false],
            ['code' => '4200', 'name' => 'مردودات المبيعات',              'type' => 'revenue', 'sub_type' => null,                  'is_header' => false],
            ['code' => '4300', 'name' => 'خصم المبيعات الممنوح',          'type' => 'revenue', 'sub_type' => null,                  'is_header' => false],
            // ── Expenses ─────────────────────────────────────────────────────
            // Cost of Sales
            ['code' => '5000', 'name' => 'تكلفة البضاعة المباعة',         'type' => 'expense', 'sub_type' => null,                  'is_header' => false],
            // Operating Expenses — 6000 is the header; 6100-6500 are its children
            ['code' => '6000', 'name' => 'المصروفات التشغيلية',           'type' => 'expense', 'sub_type' => null,                  'is_header' => true],
            ['code' => '6100', 'name' => 'مصروفات الإيجار',               'type' => 'expense', 'sub_type' => null,                  'is_header' => false],
            ['code' => '6200', 'name' => 'مصروفات الرواتب',               'type' => 'expense', 'sub_type' => null,                  'is_header' => false],
            ['code' => '6300', 'name' => 'مصروفات الخدمات',               'type' => 'expense', 'sub_type' => null,                  'is_header' => false],
            ['code' => '6400', 'name' => 'مصروفات الاستهلاك',             'type' => 'expense', 'sub_type' => null,                  'is_header' => false],
            ['code' => '6500', 'name' => 'مصروفات متنوعة',                'type' => 'expense', 'sub_type' => null,                  'is_header' => false],
        ];

        foreach ($accounts as $account) {
            DB::table('accounts')->updateOrInsert(
                ['code' => $account['code']],
                array_merge($account, [
                    'is_active'  => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ])
            );
        }

        // ── Establish parent_id hierarchy ─────────────────────────────────────
        // Parent accounts must exist before we can reference their id.
        $id1500 = DB::table('accounts')->where('code', '1500')->value('id');
        $id6000 = DB::table('accounts')->where('code', '6000')->value('id');

        if ($id1500) {
            DB::table('accounts')
                ->whereIn('code', ['1510', '1520', '1600'])
                ->update(['parent_id' => $id1500]);
        }

        if ($id6000) {
            DB::table('accounts')
                ->whereIn('code', ['6100', '6200', '6300', '6400', '6500'])
                ->update(['parent_id' => $id6000]);
        }

        // ── Accounting settings (GL account code mappings) ───────────────────
        $settings = [
            'system_name'                    => 'الميّزان',
            'store_name'                     => 'متجري',
            'account_sales_code'             => '4000',
            'account_cogs_code'              => '5000',
            'account_inventory_code'         => '1300',
            'account_cash_code'              => '1000',
            'account_bank_code'              => '1100',
            'account_ar_code'                => '1200',
            'account_ap_code'                => '2000',
            'account_salaries_code'          => '6200',
            'account_discount_code'          => '4300',
            'account_tax_payable_code'       => '2200',
            'account_retained_earnings_code'  => '3100',
            'account_salaries_payable_code'   => '2100',
            'account_input_vat_code'          => '1150',
        ];

        foreach ($settings as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        // ── ZKTeco Settings ──────────────────────────────────────
        $zkSettings = [
            'zkteco_enabled'     => '1',
            'zkteco_mode'        => 'demo', // demo أو real
            'zkteco_ip'          => '192.168.1.100',
            'zkteco_port'        => '4370',
            'zkteco_username'    => '',
            'zkteco_password'    => '',
        ];

        foreach ($zkSettings as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        // ── Branding Settings ────────────────────────────────────
        $brandingSettings = [
            'invoice_footer' => 'تم إنشاء هذه الفاتورة باستخدام نظام الميّزان - حل إدارة متكامل | للشراء والدعم: +970592676623',
            'show_system_watermark' => '1',
        ];

        foreach ($brandingSettings as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        // Core application roles must exist before PermissionSeeder runs
        Role::firstOrCreate(['name' => 'admin',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager',  'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'cashier',  'guard_name' => 'web']);

        // Seed all permissions and assign them to roles
        $this->call(PermissionSeeder::class);

        // Create users and immediately assign their Spatie role so Gate::before works
        $admin = User::firstOrCreate(
            ['email' => 'admin@demo.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('Admin@123456'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );
        $admin->syncRoles(['admin']);

        $testUser = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'role' => 'cashier',
                'is_active' => true,
            ]
        );
        $testUser->syncRoles(['cashier']);

        $this->call(DemoDataSeeder::class);
    }
}
