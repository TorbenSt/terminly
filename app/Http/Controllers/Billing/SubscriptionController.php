<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\BillingSetting;
use App\Models\Company;
use App\Models\Plan;
use App\Services\Billing\PlanLimitService;
use App\Services\Billing\UsageSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SubscriptionController extends Controller
{
    public function __construct(
        protected PlanLimitService $limits,
        protected UsageSyncService $usageSync,
    ) {}

    public function index(Request $request): Response
    {
        $company = $request->user()->company;

        if ($request->query('checkout') === 'success') {
            $this->reconcileAfterCheckout($company);
            $company->refresh();
        }

        $subscription = $company->subscription('default');

        $invoices = [];

        if ($company->hasStripeId()) {
            try {
                $invoices = collect($company->invoices())
                    ->map(fn ($invoice) => [
                        'id' => $invoice->id,
                        'date' => $invoice->date()->toDateString(),
                        'total' => $invoice->total(),
                        'status' => $invoice->status,
                        'url' => $invoice->hosted_invoice_url,
                    ])
                    ->values()
                    ->all();
            } catch (\Throwable) {
                // Stripe nicht erreichbar – Rechnungen sind nicht kritisch für die Seite.
            }
        }

        return Inertia::render('Billing/Index', [
            'company' => [
                'name' => $company->name,
                'billing_exempt' => $company->billing_exempt,
                'on_trial' => $company->onGenericTrial(),
                'trial_ends_at' => $company->trial_ends_at?->toDateString(),
                'subscribed' => $company->hasActiveSubscription(),
                'subscription_status' => $subscription?->stripe_status,
                'on_grace_period' => $subscription?->onGracePeriod() ?? false,
                'ends_at' => $subscription?->ends_at?->toDateString(),
            ],
            'currentPlan' => $company->plan,
            'effectivePlan' => $company->effectivePlan(),
            'plans' => Plan::query()->where('is_active', true)->orderBy('price_cents')->get(),
            'usage' => $this->limits->summary($company),
            'invoices' => $invoices,
            'stripeConfigured' => (bool) config('cashier.secret'),
            'prospectAddon' => [
                'price_cents' => BillingSetting::prospectSearchPriceCents(),
                'has_access' => $company->hasProspectSearchAccess(),
                'has_addon' => $company->hasProspectSearchAddon(),
                'included_in_plan' => (bool) $company->effectivePlan()?->includes_prospect_search,
            ],
        ]);
    }

    public function checkout(Request $request): SymfonyResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
        ]);

        $company = $request->user()->company;
        $plan = Plan::findOrFail($validated['plan_id']);

        if (! $plan->is_active || ! $plan->stripe_base_price_id) {
            return back()->with('error', 'Dieses Abo ist derzeit nicht buchbar.');
        }

        if (! config('cashier.secret')) {
            return back()->with('error', 'Stripe ist nicht konfiguriert.');
        }

        if ($company->hasActiveSubscription()) {
            return back()->with('error', 'Es besteht bereits ein aktives Abo. Nutzen Sie das Abo-Portal, um es zu ändern.');
        }

        $checkout = $company
            ->newSubscription('default', $plan->stripe_base_price_id)
            ->allowPromotionCodes()
            ->checkout([
                'success_url' => route('billing.index', ['checkout' => 'success']),
                'cancel_url' => route('billing.index', ['checkout' => 'cancelled']),
            ]);

        return Inertia::location($checkout->url);
    }

    public function portal(Request $request): RedirectResponse
    {
        $company = $request->user()->company;

        if (! $company->hasStripeId()) {
            return back()->with('error', 'Noch kein Stripe-Kundenkonto vorhanden.');
        }

        return $company->redirectToBillingPortal(route('billing.index'));
    }

    public function purchaseProspectAddon(Request $request): SymfonyResponse|RedirectResponse
    {
        $company = $request->user()->company;

        if ($company->hasProspectSearchAccess()) {
            return back()->with('success', 'Kundensuche ist bereits freigeschaltet.');
        }

        if (! config('cashier.secret')) {
            return back()->with('error', 'Stripe ist nicht konfiguriert.');
        }

        $priceId = BillingSetting::prospectSearchStripePriceId();

        if (! $priceId) {
            return back()->with('error', 'Kundensuche Add-on ist noch nicht konfiguriert.');
        }

        $subscription = $company->subscription('default');

        if ($subscription && $subscription->valid()) {
            $subscription->noProrate()->addPrice($priceId);

            return redirect()->route('prospects.index')->with('success', 'Kundensuche Add-on wurde gebucht.');
        }

        $checkout = $company
            ->newSubscription('default', $priceId)
            ->allowPromotionCodes()
            ->checkout([
                'success_url' => route('prospects.index', ['checkout' => 'addon_success']),
                'cancel_url' => route('billing.index', ['checkout' => 'cancelled']),
            ]);

        return Inertia::location($checkout->url);
    }

    /**
     * Nach erfolgreichem Checkout: plan_id anhand des gebuchten Basispreises setzen
     * und Überschreitungs-Items synchronisieren.
     */
    protected function reconcileAfterCheckout(Company $company): void
    {
        $subscription = $company->subscription('default');

        if (! $subscription || ! $subscription->valid()) {
            return;
        }

        $prices = $subscription->items()->pluck('stripe_price')->all();

        if ($subscription->stripe_price) {
            $prices[] = $subscription->stripe_price;
        }

        $plan = Plan::query()->whereIn('stripe_base_price_id', $prices)->first();

        if ($plan && $company->plan_id !== $plan->id) {
            $company->update(['plan_id' => $plan->id]);
        }

        $this->usageSync->sync($company->fresh());
    }
}
