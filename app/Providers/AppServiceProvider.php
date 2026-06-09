<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Customer;
use App\Models\RecurringService;
use App\Models\ServiceType;
use App\Models\StaffMember;
use App\Policies\AppointmentPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\RecurringServicePolicy;
use App\Policies\ServiceTypePolicy;
use App\Policies\StaffMemberPolicy;
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
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(ServiceType::class, ServiceTypePolicy::class);
        Gate::policy(StaffMember::class, StaffMemberPolicy::class);
        Gate::policy(Appointment::class, AppointmentPolicy::class);
        Gate::policy(RecurringService::class, RecurringServicePolicy::class);

        Gate::before(function ($user, $ability) {
            if ($user?->isSuperAdmin()) {
                return true;
            }

            return null;
        });
    }
}
