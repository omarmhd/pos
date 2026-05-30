<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes — forensic audit 2026-05-26.
 * Targets every full-table-scan hot-path identified in the audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── SALES ──────────────────────────────────────────────────────
        Schema::table('sales', function (Blueprint $table) {
            $table->index('created_at',     'idx_sales_created_at');
            $table->index('payment_method', 'idx_sales_payment_method');
            $table->index('is_credit',      'idx_sales_is_credit');
            $table->index('is_posted',      'idx_sales_is_posted');
            $table->index(['customer_id', 'is_credit'], 'idx_sales_customer_credit');
        });

        // ── PURCHASES ──────────────────────────────────────────────────
        Schema::table('purchases', function (Blueprint $table) {
            $table->index('created_at',    'idx_purchases_created_at');
            $table->index('payment_status','idx_purchases_payment_status');
            $table->index('is_posted',     'idx_purchases_is_posted');
            $table->index(['payment_status', 'supplier_id'], 'idx_purchases_status_supplier');
        });

        // ── PRODUCTS ──────────────────────────────────────────────────
        Schema::table('products', function (Blueprint $table) {
            $table->index('expiry_date',   'idx_products_expiry_date');
            $table->index('quantity',      'idx_products_quantity');
        });

        // ── CUSTOMERS ─────────────────────────────────────────────────
        Schema::table('customers', function (Blueprint $table) {
            $table->index('is_active',     'idx_customers_is_active');
            $table->index('name',          'idx_customers_name');
            $table->index('phone',         'idx_customers_phone');
        });

        // ── CUSTOMER_PAYMENTS ─────────────────────────────────────────
        Schema::table('customer_payments', function (Blueprint $table) {
            $table->index('received_at',   'idx_cpay_received_at');
        });

        // ── JOURNAL_ENTRIES ───────────────────────────────────────────
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->index('entry_date',    'idx_je_entry_date');
            $table->index('posted_at',     'idx_je_posted_at');
        });

        // ── JOURNAL_ENTRY_LINES ───────────────────────────────────────
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->index('account_id',    'idx_jel_account_id');
        });

        // ── ATTENDANCE ────────────────────────────────────────────────
        if (Schema::hasTable('attendance')) {
            Schema::table('attendance', function (Blueprint $table) {
                $table->index('work_date', 'idx_att_work_date');
            });
        }

        // ── EMPLOYEES ─────────────────────────────────────────────────
        Schema::table('employees', function (Blueprint $table) {
            $table->index('is_active',     'idx_emp_is_active');
            $table->index('department',    'idx_emp_department');
        });

        // ── SHIFTS ────────────────────────────────────────────────────
        Schema::table('shifts', function (Blueprint $table) {
            $table->index('is_active',     'idx_shifts_is_active');
        });

        // ── ACCOUNTS ──────────────────────────────────────────────────
        Schema::table('accounts', function (Blueprint $table) {
            $table->index('type',          'idx_accounts_type');
            $table->index('is_active',     'idx_accounts_is_active');
            $table->index('parent_id',     'idx_accounts_parent_id');
        });

        // ── USERS ─────────────────────────────────────────────────────
        Schema::table('users', function (Blueprint $table) {
            $table->index('is_active',     'idx_users_is_active');
        });

        // ── AUDIT_LOGS ────────────────────────────────────────────────
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('user_id',       'idx_audit_user_id');
            $table->index('created_at',    'idx_audit_created_at');
            $table->index(['auditable_type', 'auditable_id'], 'idx_audit_auditable');
        });
    }

    public function down(): void
    {
        Schema::table('sales', fn($t) => collect([
            'idx_sales_created_at','idx_sales_payment_method','idx_sales_is_credit',
            'idx_sales_is_posted','idx_sales_customer_credit',
        ])->each(fn($i) => $t->dropIndex($i)));

        Schema::table('purchases', fn($t) => collect([
            'idx_purchases_created_at','idx_purchases_payment_status',
            'idx_purchases_is_posted','idx_purchases_status_supplier',
        ])->each(fn($i) => $t->dropIndex($i)));

        Schema::table('products', fn($t) => collect([
            'idx_products_expiry_date','idx_products_quantity',
        ])->each(fn($i) => $t->dropIndex($i)));

        Schema::table('customers', fn($t) => collect([
            'idx_customers_is_active','idx_customers_name','idx_customers_phone',
        ])->each(fn($i) => $t->dropIndex($i)));

        Schema::table('customer_payments', fn($t) => $t->dropIndex('idx_cpay_received_at'));
        Schema::table('journal_entries',   fn($t) => collect(['idx_je_entry_date','idx_je_posted_at'])->each(fn($i) => $t->dropIndex($i)));
        Schema::table('journal_entry_lines', fn($t) => $t->dropIndex('idx_jel_account_id'));
        if (Schema::hasTable('attendance')) {
            Schema::table('attendance', fn($t) => $t->dropIndex('idx_att_work_date'));
        }
        Schema::table('employees',         fn($t) => collect(['idx_emp_is_active','idx_emp_department'])->each(fn($i) => $t->dropIndex($i)));
        Schema::table('shifts',            fn($t) => $t->dropIndex('idx_shifts_is_active'));
        Schema::table('accounts',          fn($t) => collect(['idx_accounts_type','idx_accounts_is_active','idx_accounts_parent_id'])->each(fn($i) => $t->dropIndex($i)));
        Schema::table('users',             fn($t) => $t->dropIndex('idx_users_is_active'));
        Schema::table('audit_logs',        fn($t) => collect(['idx_audit_user_id','idx_audit_created_at','idx_audit_auditable'])->each(fn($i) => $t->dropIndex($i)));
    }
};
