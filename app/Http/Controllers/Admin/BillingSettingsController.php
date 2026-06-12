<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BillingSettingsController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'default_trial_days' => ['required', 'integer', 'min:0', 'max:365'],
        ]);

        BillingSetting::set('default_trial_days', (string) $validated['default_trial_days']);

        return back()->with('success', 'Billing-Einstellungen gespeichert.');
    }
}
