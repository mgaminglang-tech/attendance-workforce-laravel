<?php

namespace App\Providers;

use App\Enums\AccountStatus;
use App\Enums\EmploymentStatus;
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

        Gate::define(
            'manage-workforce',
            fn (User $user): bool => $user->hasRole(UserRole::Admin)
                && $user->account_status === AccountStatus::Active,
        );

        Gate::define(
            'view-own-team-attendance',
            fn (User $user): bool => $user->hasRole(UserRole::Employee)
                && $user->account_status === AccountStatus::Active
                && $user->employee()
                    ->where('employment_status', EmploymentStatus::Active->value)
                    ->whereNotNull('department_id')
                    ->exists(),
        );

        Gate::define(
            'view-assigned-team-attendance',
            fn (User $user): bool => $user->hasRole(UserRole::Employee)
                && $user->account_status === AccountStatus::Active
                && $user->employee()
                    ->where('employment_status', EmploymentStatus::Active->value)
                    ->exists()
                && $user->hrDepartmentAssignment()->exists(),
        );

        RateLimiter::for('employee-invitation-resend', function (Request $request): Limit {
            return Limit::perMinute(3)->by('invitation-resend:'.$request->user()->getAuthIdentifier());
        });

        RateLimiter::for('employee-invitation-accept', function (Request $request): Limit {
            return Limit::perMinute(10)->by('invitation-accept:'.$request->ip());
        });
    }
}
