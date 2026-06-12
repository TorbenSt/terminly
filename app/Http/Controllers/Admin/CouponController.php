<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Cashier;

class CouponController extends Controller
{
    public function index(): Response
    {
        $coupons = [];
        $promotionCodes = [];
        $stripeConfigured = (bool) config('cashier.secret');

        if ($stripeConfigured) {
            $stripe = Cashier::stripe();

            $coupons = collect($stripe->coupons->all(['limit' => 100])->data)
                ->map(fn ($coupon) => [
                    'id' => $coupon->id,
                    'name' => $coupon->name,
                    'percent_off' => $coupon->percent_off,
                    'amount_off' => $coupon->amount_off,
                    'currency' => $coupon->currency,
                    'duration' => $coupon->duration,
                    'duration_in_months' => $coupon->duration_in_months,
                    'valid' => $coupon->valid,
                ])
                ->values();

            $promotionCodes = collect($stripe->promotionCodes->all(['limit' => 100])->data)
                ->map(fn ($code) => [
                    'id' => $code->id,
                    'code' => $code->code,
                    'coupon_id' => $code->coupon->id,
                    'coupon_name' => $code->coupon->name,
                    'active' => $code->active,
                    'times_redeemed' => $code->times_redeemed,
                ])
                ->values();
        }

        return Inertia::render('Admin/Coupons/Index', [
            'coupons' => $coupons,
            'promotionCodes' => $promotionCodes,
            'stripeConfigured' => $stripeConfigured,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! config('cashier.secret')) {
            return back()->with('error', 'Stripe ist nicht konfiguriert.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:percent,amount'],
            'percent_off' => ['required_if:type,percent', 'nullable', 'numeric', 'min:1', 'max:100'],
            'amount_off_cents' => ['required_if:type,amount', 'nullable', 'integer', 'min:1'],
            'duration' => ['required', 'in:once,repeating,forever'],
            'duration_in_months' => ['required_if:duration,repeating', 'nullable', 'integer', 'min:1', 'max:36'],
            'code' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);

        $stripe = Cashier::stripe();

        $params = [
            'name' => $validated['name'],
            'duration' => $validated['duration'],
        ];

        if ($validated['type'] === 'percent') {
            $params['percent_off'] = (float) $validated['percent_off'];
        } else {
            $params['amount_off'] = (int) $validated['amount_off_cents'];
            $params['currency'] = config('cashier.currency', 'eur');
        }

        if ($validated['duration'] === 'repeating') {
            $params['duration_in_months'] = (int) $validated['duration_in_months'];
        }

        $coupon = $stripe->coupons->create($params);

        if (! empty($validated['code'])) {
            $stripe->promotionCodes->create([
                'coupon' => $coupon->id,
                'code' => strtoupper($validated['code']),
            ]);
        }

        return back()->with('success', 'Gutschein angelegt.');
    }

    public function destroy(string $couponId): RedirectResponse
    {
        if (! config('cashier.secret')) {
            return back()->with('error', 'Stripe ist nicht konfiguriert.');
        }

        // Löschen verhindert neue Einlösungen; bereits angewendete Rabatte bleiben bestehen.
        Cashier::stripe()->coupons->delete($couponId);

        return back()->with('success', 'Gutschein gelöscht.');
    }

    public function deactivateCode(string $promotionCodeId): RedirectResponse
    {
        if (! config('cashier.secret')) {
            return back()->with('error', 'Stripe ist nicht konfiguriert.');
        }

        Cashier::stripe()->promotionCodes->update($promotionCodeId, ['active' => false]);

        return back()->with('success', 'Promo-Code deaktiviert.');
    }
}
