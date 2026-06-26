<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingSetting;
use App\Models\Company;
use App\Models\Plan;
use App\Services\Billing\PlanLimitService;
use App\Services\Billing\PlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function __construct(
        protected PlanService $planService,
        protected PlanLimitService $limits,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Company::class);

        $companies = Company::query()
            ->with(['plan:id,name', 'subscriptions'])
            ->withCount([
                'staffMembers as active_staff_count' => fn ($query) => $query->where('is_active', true),
                'customers as active_customers_count' => fn ($query) => $query->where('is_active', true),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Company $company) => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'email' => $company->email,
                'phone' => $company->phone,
                'timezone' => $company->timezone,
                'is_active' => $company->is_active,
                'plan_id' => $company->plan_id,
                'plan_name' => $company->plan?->name,
                'billing_exempt' => $company->billing_exempt,
                'trial_ends_at' => $company->trial_ends_at?->toDateString(),
                'on_trial' => $company->onGenericTrial(),
                'subscribed' => $company->hasActiveSubscription(),
                'staff_limit_override' => $company->staff_limit_override,
                'customer_limit_override' => $company->customer_limit_override,
                'active_staff_count' => $company->active_staff_count,
                'active_customers_count' => $company->active_customers_count,
                'staff_limit' => $this->limits->staffLimit($company),
                'customer_limit' => $this->limits->customerLimit($company),
                'prospect_search_override' => $company->prospect_search_override,
                'has_prospect_search' => $company->hasProspectSearchAccess(),
            ]);

        return Inertia::render('Admin/Companies/Index', [
            'companies' => $companies,
            'plans' => Plan::query()->orderBy('price_cents')->get(['id', 'name', 'is_active', 'is_default']),
            'defaultTrialDays' => BillingSetting::defaultTrialDays(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Company::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'timezone' => ['required', 'string', 'max:64'],
        ]);

        $trialDays = BillingSetting::defaultTrialDays();

        Company::create([
            ...$validated,
            'slug' => Str::slug($validated['name']).'-'.Str::random(4),
            'trial_ends_at' => $trialDays > 0 ? now()->addDays($trialDays) : null,
        ]);

        return back()->with('success', 'Unternehmen angelegt.');
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $this->authorize('update', $company);

        $validated = $request->validate([
            'plan_id' => ['nullable', 'integer', 'exists:plans,id'],
            'billing_exempt' => ['boolean'],
            'is_active' => ['boolean'],
            // -1 = unendlich, null = Plan-Wert
            'staff_limit_override' => ['nullable', 'integer', 'min:-1'],
            'customer_limit_override' => ['nullable', 'integer', 'min:-1'],
            'prospect_search_override' => ['nullable', 'boolean'],
            'trial_ends_at' => ['nullable', 'date'],
        ]);

        $planChanged = array_key_exists('plan_id', $validated)
            && (int) $validated['plan_id'] !== (int) $company->plan_id;

        $company->update(collect($validated)->except('plan_id')->all());

        if ($planChanged) {
            $plan = $validated['plan_id'] ? Plan::find($validated['plan_id']) : null;
            $this->planService->changeCompanyPlan($company, $plan);
        }

        return back()->with('success', 'Unternehmen aktualisiert.');
    }
}
