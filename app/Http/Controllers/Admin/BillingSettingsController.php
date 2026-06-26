<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingSetting;
use App\Services\Billing\ProspectAddonService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BillingSettingsController extends Controller
{
    public function __construct(protected ProspectAddonService $prospectAddon) {}

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'default_trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'prospect_search_price_cents' => ['nullable', 'integer', 'min:0'],
        ]);

        BillingSetting::set('default_trial_days', (string) $validated['default_trial_days']);

        if (array_key_exists('prospect_search_price_cents', $validated) && $validated['prospect_search_price_cents'] !== null) {
            BillingSetting::set('prospect_search_price_cents', (string) $validated['prospect_search_price_cents']);
            $this->prospectAddon->syncStripePrice();
        }

        return back()->with('success', 'Billing-Einstellungen gespeichert.');
    }
}
