<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Gate::define('manage-workforce', fn (User $user): bool => $user->hasRole(UserRole::Admin));

        RateLimiter::for('employee-invitation-resend', function (Request $request): Limit {
            return Limit::perMinute(3)->by('invitation-resend:'.$request->user()->getAuthIdentifier());
        });

        RateLimiter::for('employee-invitation-accept', function (Request $request): Limit {
            return Limit::perMinute(10)->by('invitation-accept:'.$request->ip());
        });
    }
}
