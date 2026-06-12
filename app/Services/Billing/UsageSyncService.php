<?php

namespace App\Services\Billing;

use App\Models\Company;

class UsageSyncService
{
    public function __construct(protected PlanLimitService $limits) {}

    /**
     * Gleicht die Überschreitungs-Mengen (zusätzliche Mitarbeiter/Kunden) mit den
     * Stripe-Subscription-Items ab. Ohne Proration: Abrechnung mit der nächsten Rechnung.
     */
    public function sync(Company $company): void
    {
        if ($company->billing_exempt || ! config('cashier.secret')) {
            return;
        }

        $subscription = $company->subscription('default');

        if (! $subscription || ! $subscription->valid()) {
            return;
        }

        $plan = $company->plan;

        if (! $plan) {
            return;
        }

        $targets = [
            ['price' => $plan->stripe_staff_price_id, 'quantity' => $this->limits->staffOverage($company)],
            ['price' => $plan->stripe_customer_price_id, 'quantity' => $this->limits->customerOverage($company)],
        ];

        foreach ($targets as $target) {
            if (! $target['price']) {
                continue;
            }

            $item = $subscription->items()->where('stripe_price', $target['price'])->first();

            if ($item) {
                if ((int) $item->quantity !== $target['quantity']) {
                    $subscription->noProrate()->updateQuantity($target['quantity'], $target['price']);
                }
            } elseif ($target['quantity'] > 0) {
                $subscription->noProrate()->addPrice($target['price'], $target['quantity']);
            }
        }
    }
}
