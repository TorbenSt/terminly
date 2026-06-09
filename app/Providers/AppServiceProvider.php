<?php

namespace App\Providers;

use App\Models\Company;
use App\Policies\CompanyPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::policy(Company::class, CompanyPolicy::class);

        Gate::before(function ($user, $ability) {
            if ($user?->isSuperAdmin()) {
                return true;
            }

            return null;
        });
    }
}
