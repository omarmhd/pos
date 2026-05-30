<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Reversal;
use App\Policies\ReversalPolicy;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
            Schema::defaultStringLength(191);

        // Super-admin bypass: admin role passes every Gate check without needing
        // individual permissions. All other roles are checked against permissions.
        Gate::before(function ($user, $ability) {
            if ($user->hasRole('admin')) {
                return true;
            }
        });

        Gate::policy(Reversal::class, ReversalPolicy::class);
    }
}
