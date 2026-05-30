<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Reversal;
use App\Policies\ReversalPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

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
    }
}
