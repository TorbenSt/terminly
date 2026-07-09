<?php

namespace App\Http\Middleware;

use App\Services\Billing\PlanLimitService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'company_id' => $user->company_id,
                    'roles' => $user->getRoleNames(),
                    'is_super_admin' => $user->isSuperAdmin(),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'billing' => fn () => $this->billingStatus($request),
            'prospectSearch' => fn () => $this->prospectSearchStatus($request),
            'schedulingLab' => fn () => [
                'enabled' => (bool) config('scheduling_lab.enabled'),
            ],
        ];
    }

    protected function prospectSearchStatus(Request $request): ?array
    {
        $user = $request->user();

        if (! $user || $user->isSuperAdmin() || ! $user->company_id || ! $user->isCompanyAdmin()) {
            return null;
        }

        $company = $user->company;

        if (! $company) {
            return null;
        }

        return [
            'has_access' => $company->hasProspectSearchAccess(),
            'has_addon' => $company->hasProspectSearchAddon(),
            'included_in_plan' => (bool) $company->effectivePlan()?->includes_prospect_search,
        ];
    }

    protected function billingStatus(Request $request): ?array
    {
        $user = $request->user();

        if (! $user || $user->isSuperAdmin() || ! $user->company_id) {
            return null;
        }

        $company = $user->company;

        if (! $company) {
            return null;
        }

        $limits = app(PlanLimitService::class);

        return [
            'exempt' => $company->billing_exempt,
            'on_trial' => $company->onGenericTrial(),
            'trial_ends_at' => $company->trial_ends_at?->toDateString(),
            'subscribed' => $company->hasActiveSubscription(),
            'read_only' => ! $company->hasFullAccess(),
            'usage' => $company->billing_exempt ? null : $limits->summary($company),
        ];
    }
}
