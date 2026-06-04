<?php

namespace App\Providers;

use App\Observers\AuditObserver;
use App\Observers\ProductCostObserver;
use Illuminate\Support\ServiceProvider;
use App\Models\Reversal;
use App\Policies\ReversalPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
            Schema::defaultStringLength(191);

        // Super-admin bypass: admin passes every Gate check.
        // Checks both the Spatie pivot role AND the plain users.role column
        // so the bypass works even when the Spatie role wasn't fully synced.
        Gate::before(function ($user, $ability) {
            if ($user->role === 'admin' || $user->hasRole('admin')) {
                return true;
            }
        });

        Gate::policy(Reversal::class, ReversalPolicy::class);

        // ── Comprehensive Audit Trail ────────────────────────────────────────
        // Every create/update/delete on these models is logged automatically.
        // The observer skips noise (updated_at, transient PENDING-* values).
        \App\Models\Sale::observe(AuditObserver::class);
        \App\Models\Purchase::observe(AuditObserver::class);
        \App\Models\JournalEntry::observe(AuditObserver::class);
        \App\Models\JournalEntryLine::observe(AuditObserver::class);
        \App\Models\CustomerPayment::observe(AuditObserver::class);
        \App\Models\SupplierPayment::observe(AuditObserver::class);
        \App\Models\ReceiptVoucher::observe(AuditObserver::class);
        \App\Models\PaymentVoucher::observe(AuditObserver::class);
        \App\Models\Customer::observe(AuditObserver::class);
        \App\Models\Supplier::observe(AuditObserver::class);
        \App\Models\InventoryAdjustment::observe(AuditObserver::class);
        \App\Models\FixedAsset::observe(AuditObserver::class);

        // Track manual cost_price changes on products (AVCO changes logged in PurchaseItem boot)
        \App\Models\Product::observe(ProductCostObserver::class);

        // Share $currency with every view so no controller needs to pass it manually.
        // Setting::get() is cached (1-day TTL) so this is a single cache lookup per request.
        View::composer('*', function ($view) {
            try {
                $view->with('currency', \App\Models\Setting::get('currency_symbol', 'ج.م'));
            } catch (\Throwable $e) {
                // Silently skip during migrations or when DB is unavailable
                $view->with('currency', 'ج.م');
            }
        });
    }
}
