<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingSetting;
use App\Models\Plan;
use App\Services\Billing\PlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function __construct(protected PlanService $planService) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Plans/Index', [
            'plans' => Plan::query()
                ->withCount('companies')
                ->orderByDesc('is_default')
                ->orderBy('price_cents')
                ->get(),
            'defaultTrialDays' => BillingSetting::defaultTrialDays(),
            'prospectSearchPriceCents' => BillingSetting::prospectSearchPriceCents(),
            'stripeConfigured' => (bool) config('cashier.secret'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->planService->createPlan($this->validated($request));

        return back()->with('success', 'Abo angelegt.');
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $this->planService->updatePlan($plan, $this->validated($request));

        return back()->with('success', 'Abo aktualisiert.');
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        if ($plan->companies()->exists()) {
            $this->planService->deactivatePlan($plan);

            return back()->with('success', 'Abo wird von Firmen genutzt und wurde deshalb deaktiviert.');
        }

        $this->planService->deactivatePlan($plan);
        $plan->delete();

        return back()->with('success', 'Abo gelöscht.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'included_staff' => ['nullable', 'integer', 'min:0'],
            'included_customers' => ['nullable', 'integer', 'min:0'],
            'extra_staff_price_cents' => ['required', 'integer', 'min:0'],
            'extra_customer_price_cents' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
            'includes_prospect_search' => ['boolean'],
            'max_prospect_results_per_run' => ['nullable', 'integer', 'min:1', 'max:200'],
            'prospect_outreach_limit_per_day' => ['nullable', 'integer', 'min:0', 'max:500'],
        ]);
    }
}
