<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles & permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Create all permissions ──────────────────────────────────────────
        $allKeys = [];
        foreach (config('permission_groups') as $module) {
            foreach (array_keys($module['permissions']) as $key) {
                Permission::firstOrCreate(['name' => $key, 'guard_name' => 'web']);
                $allKeys[] = $key;
            }
        }

        // Keep backward-compat alias so existing data stays valid
        Permission::firstOrCreate(['name' => 'create reversal', 'guard_name' => 'web']);

        // ── Ensure all roles exist ──────────────────────────────────────────
        $admin          = Role::firstOrCreate(['name' => 'admin',           'guard_name' => 'web']);
        $manager        = Role::firstOrCreate(['name' => 'manager',         'guard_name' => 'web']);
        $cashier        = Role::firstOrCreate(['name' => 'cashier',         'guard_name' => 'web']);
        $accountant     = Role::firstOrCreate(['name' => 'accountant',      'guard_name' => 'web']);
        $reversalMgr    = Role::firstOrCreate(['name' => 'reversal_manager','guard_name' => 'web']);

        // ── Admin — all permissions ─────────────────────────────────────────
        $admin->syncPermissions($allKeys);

        // ── Manager ────────────────────────────────────────────────────────
        $manager->syncPermissions([
            'dashboard.view',
            // Sales
            'sales.view', 'sales.create', 'sales.edit', 'sales.delete', 'sales.reverse',
            // Purchases
            'purchases.view', 'purchases.create', 'purchases.edit', 'purchases.delete',
            // Customers
            'customers.view', 'customers.create', 'customers.edit', 'customers.delete', 'customers.payments',
            // Suppliers
            'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete', 'suppliers.payments',
            // Products & Categories
            'products.view', 'products.create', 'products.edit', 'products.delete',
            'categories.view', 'categories.create', 'categories.edit', 'categories.delete',
            // Inventory & Stocktaking
            'inventory.view', 'inventory.adjust', 'inventory.count',
            // Vouchers
            'vouchers.view', 'vouchers.create', 'vouchers.delete',
            // Accounting (read + basic)
            'accounts.view',
            'journal_entries.view',
            'ledger.view', 'trial_balance.view', 'financial_statements.view',
            // Reports
            'reports.view',
            // HR
            'hr.view_employees', 'hr.create_employees', 'hr.edit_employees',
            'hr.view_payroll', 'hr.create_payroll', 'hr.approve_payroll',
            'hr.view_attendance', 'hr.manage_attendance',
            'hr.view_shifts', 'hr.manage_shifts',
            // Reversals
            'reversals.view', 'reversals.create',
        ]);

        // ── Cashier ─────────────────────────────────────────────────────────
        $cashier->syncPermissions([
            'dashboard.view',
            'sales.view', 'sales.create',
            'customers.view', 'customers.create',
            'products.view',
            'inventory.view',
        ]);

        // ── Accountant ──────────────────────────────────────────────────────
        $accountant->syncPermissions([
            'dashboard.view',
            'sales.view', 'sales.post',
            'purchases.view', 'purchases.post',
            'customers.view', 'customers.payments',
            'suppliers.view', 'suppliers.payments',
            'vouchers.view', 'vouchers.create', 'vouchers.delete',
            'accounts.view', 'accounts.create', 'accounts.edit', 'accounts.delete', 'accounts.import',
            'journal_entries.view', 'journal_entries.create',
            'ledger.view', 'trial_balance.view', 'financial_statements.view',
            'accounting.year_end_close', 'accounting.post', 'accounting.reverse',
            'reports.view',
            'audit_logs.view',
            'reversals.view', 'reversals.create',
        ]);

        // ── Reversal Manager (backward compat) ──────────────────────────────
        $reversalMgr->syncPermissions([
            'reversals.view', 'reversals.create',
            'create reversal', // legacy alias kept alive
        ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
