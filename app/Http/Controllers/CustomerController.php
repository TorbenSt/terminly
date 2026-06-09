<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\ServiceType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Customer::class);

        return Inertia::render('Customers/Index', [
            'customers' => Customer::query()
                ->with(['recurringServices.serviceType'])
                ->orderBy('name')
                ->paginate(15)
                ->through(fn (Customer $customer) => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'address' => $customer->address,
                    'postal_code' => $customer->postal_code,
                    'city' => $customer->city,
                    'notes' => $customer->notes,
                    'is_active' => $customer->is_active,
                    'recurring_services' => $customer->recurringServices->map(fn ($rs) => [
                        'id' => $rs->id,
                        'service_type_id' => $rs->service_type_id,
                        'service_name' => $rs->serviceType->name,
                        'is_recurring' => $rs->serviceType->is_recurring,
                        'duration_minutes' => $rs->serviceType->duration_minutes,
                        'next_due_at' => $rs->next_due_at->toDateString(),
                        'is_active' => $rs->is_active,
                        'is_due' => $rs->next_due_at->lte(now()),
                    ]),
                ]),
            'serviceTypes' => ServiceType::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'duration_minutes', 'is_recurring']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:10'],
            'city' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        Customer::create($validated);

        return back()->with('success', 'Kunde angelegt.');
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:10'],
            'city' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $customer->update($validated);

        return back()->with('success', 'Kunde aktualisiert.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        if ($customer->recurringServices()->exists() || $customer->appointments()->exists()) {
            return back()->with('error', 'Kunde hat noch Wartungen oder Termine und kann nicht gelöscht werden.');
        }

        $customer->delete();

        return back()->with('success', 'Kunde gelöscht.');
    }
}
