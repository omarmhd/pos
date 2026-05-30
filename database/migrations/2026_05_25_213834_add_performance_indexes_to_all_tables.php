<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            if (! $this->hasIndex('sales', 'idx_sales_created_at')) {
                $table->index('created_at', 'idx_sales_created_at');
            }
            if (! $this->hasIndex('sales', 'idx_sales_payment_method')) {
                $table->index('payment_method', 'idx_sales_payment_method');
            }
            if (! $this->hasIndex('sales', 'idx_sales_is_credit')) {
                $table->index('is_credit', 'idx_sales_is_credit');
            }
            if (! $this->hasIndex('sales', 'idx_sales_is_posted')) {
                $table->index('is_posted', 'idx_sales_is_posted');
            }
            if (! $this->hasIndex('sales', 'idx_sales_customer_credit')) {
                $table->index(['customer_id', 'is_credit'], 'idx_sales_customer_credit');
            }
        });

        // ── PURCHASES ──────────────────────────────────────────────────
        Schema::table('purchases', function (Blueprint $table) {
            if (! $this->hasIndex('purchases', 'idx_purchases_created_at')) {
                $table->index('created_at', 'idx_purchases_created_at');
            }
            if (! $this->hasIndex('purchases', 'idx_purchases_payment_status')) {
                $table->index('payment_status', 'idx_purchases_payment_status');
            }
            if (! $this->hasIndex('purchases', 'idx_purchases_is_posted')) {
                $table->index('is_posted', 'idx_purchases_is_posted');
            }
            if (! $this->hasIndex('purchases', 'idx_purchases_status_supplier')) {
                $table->index(['payment_status', 'supplier_id'], 'idx_purchases_status_supplier');
            }
        });

        // ── PRODUCTS ──────────────────────────────────────────────────
        Schema::table('products', function (Blueprint $table) {
            if (! $this->hasIndex('products', 'idx_products_expiry_date')) {
                $table->index('expiry_date', 'idx_products_expiry_date');
            }
            if (! $this->hasIndex('products', 'idx_products_quantity')) {
                $table->index('quantity', 'idx_products_quantity');
            }
        });

        // ── CUSTOMERS ─────────────────────────────────────────────────
        Schema::table('customers', function (Blueprint $table) {
            if (! $this->hasIndex('customers', 'idx_customers_is_active')) {
                $table->index('is_active', 'idx_customers_is_active');
            }
            if (! $this->hasIndex('customers', 'idx_customers_name')) {
                $table->index('name', 'idx_customers_name');
            }
            if (! $this->hasIndex('customers', 'idx_customers_phone')) {
                $table->index('phone', 'idx_customers_phone');
            }
        });

        // ── CUSTOMER_PAYMENTS ─────────────────────────────────────────
        Schema::table('customer_payments', function (Blueprint $table) {
            if (! $this->hasIndex('customer_payments', 'idx_cpay_received_at')) {
                $table->index('received_at', 'idx_cpay_received_at');
            }
        });

        // ── JOURNAL_ENTRIES ───────────────────────────────────────────
        Schema::table('journal_entries', function (Blueprint $table) {
            if (! $this->hasIndex('journal_entries', 'idx_je_entry_date')) {
                $table->index('entry_date', 'idx_je_entry_date');
            }
            if (! $this->hasIndex('journal_entries', 'idx_je_posted_at')) {
                $table->index('posted_at', 'idx_je_posted_at');
            }
        });

        // ── JOURNAL_ENTRY_LINES ───────────────────────────────────────
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            if (! $this->hasIndex('journal_entry_lines', 'idx_jel_account_id')) {
                $table->index('account_id', 'idx_jel_account_id');
            }
        });

        // ── ATTENDANCE ────────────────────────────────────────────────
        if (Schema::hasTable('attendance')) {
            Schema::table('attendance', function (Blueprint $table) {
                if (! $this->hasIndex('attendance', 'idx_att_work_date')) {
                    $table->index('work_date', 'idx_att_work_date');
                }
            });
        }

        // ── EMPLOYEES ─────────────────────────────────────────────────
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                if (! $this->hasIndex('employees', 'idx_emp_is_active')) {
                    $table->index('is_active', 'idx_emp_is_active');
                }
                if (! $this->hasIndex('employees', 'idx_emp_department')) {
                    $table->index('department', 'idx_emp_department');
                }
            });
        }

        // ── SHIFTS ────────────────────────────────────────────────────
        if (Schema::hasTable('shifts')) {
            Schema::table('shifts', function (Blueprint $table) {
                if (! $this->hasIndex('shifts', 'idx_shifts_is_active')) {
                    $table->index('is_active', 'idx_shifts_is_active');
                }
            });
        }

        // ── ACCOUNTS ──────────────────────────────────────────────────
        Schema::table('accounts', function (Blueprint $table) {
            if (! $this->hasIndex('accounts', 'idx_accounts_type')) {
                $table->index('type', 'idx_accounts_type');
            }
            if (! $this->hasIndex('accounts', 'idx_accounts_is_active')) {
                $table->index('is_active', 'idx_accounts_is_active');
            }
            if (! $this->hasIndex('accounts', 'idx_accounts_parent_id')) {
                $table->index('parent_id', 'idx_accounts_parent_id');
            }
        });

        // ── USERS ─────────────────────────────────────────────────────
        Schema::table('users', function (Blueprint $table) {
            if (! $this->hasIndex('users', 'idx_users_is_active')) {
                $table->index('is_active', 'idx_users_is_active');
            }
        });

        // ── AUDIT_LOGS ────────────────────────────────────────────────
        Schema::table('audit_logs', function (Blueprint $table) {
            if (! $this->hasIndex('audit_logs', 'idx_audit_user_id')) {
                $table->index('user_id', 'idx_audit_user_id');
            }
            if (! $this->hasIndex('audit_logs', 'idx_audit_created_at')) {
                $table->index('created_at', 'idx_audit_created_at');
            }
            if (! $this->hasIndex('audit_logs', 'idx_audit_auditable')) {
                $table->index(['auditable_type', 'auditable_id'], 'idx_audit_auditable');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', fn($t) => collect([
            'idx_sales_created_at','idx_sales_payment_method','idx_sales_is_credit',
            'idx_sales_is_posted','idx_sales_customer_credit',
        ])->each(fn($i) => $this->dropIndexIfExists('sales', $i)));

        Schema::table('purchases', fn($t) => collect([
            'idx_purchases_created_at','idx_purchases_payment_status',
            'idx_purchases_is_posted','idx_purchases_status_supplier',
        ])->each(fn($i) => $this->dropIndexIfExists('purchases', $i)));

        Schema::table('products', fn($t) => collect([
            'idx_products_expiry_date','idx_products_quantity',
        ])->each(fn($i) => $this->dropIndexIfExists('products', $i)));

        Schema::table('customers', fn($t) => collect([
            'idx_customers_is_active','idx_customers_name','idx_customers_phone',
        ])->each(fn($i) => $this->dropIndexIfExists('customers', $i)));

        $this->dropIndexIfExists('customer_payments', 'idx_cpay_received_at');
        Schema::table('journal_entries', fn($t) => collect(['idx_je_entry_date','idx_je_posted_at'])->each(fn($i) => $this->dropIndexIfExists('journal_entries', $i)));
        $this->dropIndexIfExists('journal_entry_lines', 'idx_jel_account_id');
        if (Schema::hasTable('attendance')) {
            $this->dropIndexIfExists('attendance', 'idx_att_work_date');
        }
        if (Schema::hasTable('employees')) {
            collect(['idx_emp_is_active','idx_emp_department'])->each(fn($i) => $this->dropIndexIfExists('employees', $i));
        }
        if (Schema::hasTable('shifts')) {
            $this->dropIndexIfExists('shifts', 'idx_shifts_is_active');
        }
        Schema::table('accounts', fn($t) => collect(['idx_accounts_type','idx_accounts_is_active','idx_accounts_parent_id'])->each(fn($i) => $this->dropIndexIfExists('accounts', $i)));
        $this->dropIndexIfExists('users', 'idx_users_is_active');
        Schema::table('audit_logs', fn($t) => collect(['idx_audit_user_id','idx_audit_created_at','idx_audit_auditable'])->each(fn($i) => $this->dropIndexIfExists('audit_logs', $i)));
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! $this->hasIndex($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
            $blueprint->dropIndex($indexName);
        });
    }
};
