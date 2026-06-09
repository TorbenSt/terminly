<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Models\Customer;
use App\Models\RecurringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerRecurringServiceController extends Controller
{
    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('create', RecurringService::class);
        abort_unless($customer->company_id === $request->user()->company_id, 403);

        $validated = $request->validate([
            'service_type_id' => [
                'required',
                'integer',
                Rule::exists('service_types', 'id')->where(fn ($q) => $q
                    ->where('company_id', $customer->company_id)
                    ->where('is_active', true)),
            ],
            'next_due_at' => ['required', 'date'],
        ]);

        $exists = RecurringService::query()
            ->where('customer_id', $customer->id)
            ->where('service_type_id', $validated['service_type_id'])
            ->exists();

        if ($exists) {
            return back()->with('error', 'Diese Serviceart ist dem Kunden bereits zugewiesen.');
        }

        RecurringService::create([
            'company_id' => $customer->company_id,
            'customer_id' => $customer->id,
            'service_type_id' => $validated['service_type_id'],
            'next_due_at' => $validated['next_due_at'],
            'is_active' => true,
        ]);

        return back()->with('success', 'Service dem Kunden zugewiesen.');
    }

    public function update(Request $request, Customer $customer, RecurringService $recurringService): RedirectResponse
    {
        $this->authorize('update', $recurringService);
        abort_unless($recurringService->customer_id === $customer->id, 404);

        $validated = $request->validate([
            'next_due_at' => ['required', 'date'],
            'is_active' => ['boolean'],
        ]);

        $recurringService->update($validated);

        return back()->with('success', 'Service-Zuweisung aktualisiert.');
    }

    public function destroy(Customer $customer, RecurringService $recurringService): RedirectResponse
    {
        $this->authorize('delete', $recurringService);
        abort_unless($recurringService->customer_id === $customer->id, 404);

        if ($recurringService->appointments()->whereIn('status', [
            AppointmentStatus::Proposed,
            AppointmentStatus::Confirmed,
            AppointmentStatus::Negotiation,
        ])->exists()) {
            return back()->with('error', 'Service hat offene Termine und kann nicht entfernt werden.');
        }

        $recurringService->delete();

        return back()->with('success', 'Service-Zuweisung entfernt.');
    }
}
