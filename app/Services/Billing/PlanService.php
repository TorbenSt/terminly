<?php

namespace App\Services\Billing;

use App\Models\Company;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;
use Stripe\StripeClient;

class PlanService
{
    public function createPlan(array $data): Plan
    {
        return DB::transaction(function () use ($data) {
            if (! empty($data['is_default'])) {
                Plan::query()->update(['is_default' => false]);
            }

            $plan = Plan::create($data);

            $this->syncToStripe($plan);

            return $plan;
        });
    }

    public function updatePlan(Plan $plan, array $data): Plan
    {
        return DB::transaction(function () use ($plan, $data) {
            if (! empty($data['is_default'])) {
                Plan::query()->whereKeyNot($plan->id)->update(['is_default' => false]);
            }

            $priceChanged = [
                'base' => array_key_exists('price_cents', $data) && (int) $data['price_cents'] !== $plan->price_cents,
                'staff' => array_key_exists('extra_staff_price_cents', $data) && (int) $data['extra_staff_price_cents'] !== $plan->extra_staff_price_cents,
                'customer' => array_key_exists('extra_customer_price_cents', $data) && (int) $data['extra_customer_price_cents'] !== $plan->extra_customer_price_cents,
            ];

            $plan->update($data);

            $this->syncToStripe($plan, $priceChanged);

            return $plan;
        });
    }

    /**
     * Plan deaktivieren (für neue Buchungen nicht mehr wählbar); Stripe-Produkt wird archiviert.
     */
    public function deactivatePlan(Plan $plan): void
    {
        $plan->update(['is_active' => false]);

        $stripe = $this->stripe();

        if ($stripe && $plan->stripe_product_id) {
            try {
                $stripe->products->update($plan->stripe_product_id, ['active' => false]);
            } catch (\Throwable $e) {
                Log::warning('Stripe-Produkt konnte nicht archiviert werden.', ['plan_id' => $plan->id, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Legt fehlende Stripe-Produkte/-Preise an. Da Stripe-Preise unveränderlich sind,
     * wird bei einer Preisänderung ein neuer Price erzeugt und der alte archiviert;
     * bestehende Abos behalten den alten Preis.
     *
     * @param  array{base?: bool, staff?: bool, customer?: bool}  $priceChanged
     */
    public function syncToStripe(Plan $plan, array $priceChanged = []): void
    {
        $stripe = $this->stripe();

        if (! $stripe) {
            Log::info('Stripe nicht konfiguriert – Plan wird nur lokal gespeichert.', ['plan_id' => $plan->id]);

            return;
        }

        if (! $plan->stripe_product_id) {
            $product = $stripe->products->create([
                'name' => $plan->name,
                'metadata' => ['plan_id' => (string) $plan->id],
            ]);
            $plan->forceFill(['stripe_product_id' => $product->id])->save();
        } else {
            $stripe->products->update($plan->stripe_product_id, ['name' => $plan->name, 'active' => true]);
        }

        $prices = [
            'base' => ['column' => 'stripe_base_price_id', 'amount' => $plan->price_cents, 'nickname' => 'Basis'],
            'staff' => ['column' => 'stripe_staff_price_id', 'amount' => $plan->extra_staff_price_cents, 'nickname' => 'Zusätzlicher Mitarbeiter'],
            'customer' => ['column' => 'stripe_customer_price_id', 'amount' => $plan->extra_customer_price_cents, 'nickname' => 'Zusätzlicher Kunde'],
        ];

        foreach ($prices as $key => $config) {
            $column = $config['column'];
            $needsNewPrice = ! $plan->{$column} || ! empty($priceChanged[$key]);

            if (! $needsNewPrice) {
                continue;
            }

            if ($plan->{$column}) {
                try {
                    $stripe->prices->update($plan->{$column}, ['active' => false]);
                } catch (\Throwable $e) {
                    Log::warning('Alter Stripe-Preis konnte nicht archiviert werden.', ['plan_id' => $plan->id, 'price' => $plan->{$column}, 'error' => $e->getMessage()]);
                }
            }

            $price = $stripe->prices->create([
                'product' => $plan->stripe_product_id,
                'currency' => $plan->currency,
                'unit_amount' => $config['amount'],
                'recurring' => ['interval' => 'month'],
                'nickname' => $config['nickname'],
                'metadata' => ['plan_id' => (string) $plan->id, 'type' => $key],
            ]);

            $plan->forceFill([$column => $price->id])->save();
        }
    }

    /**
     * Weist einer Firma einen (anderen) Plan zu. Besteht bereits ein aktives Abo,
     * wird der Basispreis getauscht und die Überschreitungs-Items neu synchronisiert.
     */
    public function changeCompanyPlan(Company $company, ?Plan $plan): void
    {
        $company->update(['plan_id' => $plan?->id]);

        $subscription = $company->subscription('default');

        if ($plan && $plan->stripe_base_price_id && $subscription && $subscription->valid() && config('cashier.secret')) {
            $subscription->noProrate()->swap([
                $plan->stripe_base_price_id => ['quantity' => 1],
            ]);

            app(UsageSyncService::class)->sync($company->fresh());
        }
    }

    protected function stripe(): ?StripeClient
    {
        if (! config('cashier.secret')) {
            return null;
        }

        return Cashier::stripe();
    }
}
