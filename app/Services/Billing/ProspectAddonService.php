<?php

namespace App\Services\Billing;

use App\Models\BillingSetting;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;
use Stripe\StripeClient;

class ProspectAddonService
{
    public function syncStripePrice(): void
    {
        $stripe = $this->stripe();

        if (! $stripe) {
            return;
        }

        $productId = BillingSetting::prospectSearchStripeProductId();

        if (! $productId) {
            $product = $stripe->products->create([
                'name' => 'Kundensuche Add-on',
                'metadata' => ['type' => 'prospect_search_addon'],
            ]);
            BillingSetting::set('prospect_search_stripe_product_id', $product->id);
            $productId = $product->id;
        }

        $priceCents = BillingSetting::prospectSearchPriceCents();
        $currentPriceId = BillingSetting::prospectSearchStripePriceId();

        if ($currentPriceId) {
            try {
                $current = $stripe->prices->retrieve($currentPriceId);
                if ((int) $current->unit_amount === $priceCents && $current->active) {
                    return;
                }
                $stripe->prices->update($currentPriceId, ['active' => false]);
            } catch (\Throwable $e) {
                Log::warning('Could not archive old prospect add-on price.', ['error' => $e->getMessage()]);
            }
        }

        $price = $stripe->prices->create([
            'product' => $productId,
            'currency' => config('cashier.currency', 'eur'),
            'unit_amount' => $priceCents,
            'recurring' => ['interval' => 'month'],
            'nickname' => 'Kundensuche Add-on',
        ]);

        BillingSetting::set('prospect_search_stripe_price_id', $price->id);
    }

    protected function stripe(): ?StripeClient
    {
        if (! config('cashier.secret')) {
            return null;
        }

        return Cashier::stripe();
    }
}
