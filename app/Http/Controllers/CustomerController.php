<?php

namespace App\Http\Controllers;

use App\Enums\StaffCustomerBinding;
use App\Models\Customer;
use App\Models\ServiceType;
use App\Models\StaffMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Customer::class);

        $company = $request->user()->company;
        $staffMembers = StaffMember::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'user_id']);

        $currentStaffMemberId = $staffMembers
            ->firstWhere('user_id', $request->user()->id)
            ?->id;

        return Inertia::render('Customers/Index', [
            'customers' => Customer::query()
                ->with(['recurringServices.serviceType', 'primaryStaffMember:id,name', 'backupStaffMember:id,name'])
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
                    'primary_staff_member_id' => $customer->primary_staff_member_id,
                    'backup_staff_member_id' => $customer->backup_staff_member_id,
                    'primary_staff_name' => $customer->primaryStaffMember?->name,
                    'backup_staff_name' => $customer->backupStaffMember?->name,
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
            'staffMembers' => $staffMembers->map(fn (StaffMember $staff) => [
                'id' => $staff->id,
                'name' => $staff->name,
            ]),
            'currentStaffMemberId' => $currentStaffMemberId,
            'staffCustomerBinding' => $company?->staff_customer_binding?->value
                ?? StaffCustomerBinding::Prefer->value,
            'staffCustomerBindingOptions' => StaffCustomerBinding::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $request->merge([
            'primary_staff_member_id' => $request->input('primary_staff_member_id') ?: null,
            'backup_staff_member_id' => $request->input('backup_staff_member_id') ?: null,
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:10'],
            'city' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'primary_staff_member_id' => ['nullable', 'integer', Rule::exists('staff_members', 'id')->where('company_id', $request->user()->company_id)],
            'backup_staff_member_id' => ['nullable', 'integer', Rule::exists('staff_members', 'id')->where('company_id', $request->user()->company_id)],
        ]);

        Customer::create($validated);

        return back()->with('success', 'Kunde angelegt.');
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $request->merge([
            'primary_staff_member_id' => $request->input('primary_staff_member_id') ?: null,
            'backup_staff_member_id' => $request->input('backup_staff_member_id') ?: null,
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:10'],
            'city' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'primary_staff_member_id' => ['nullable', 'integer', Rule::exists('staff_members', 'id')->where('company_id', $customer->company_id)],
            'backup_staff_member_id' => ['nullable', 'integer', Rule::exists('staff_members', 'id')->where('company_id', $customer->company_id)],
        ]);

        $customer->update($validated);

        return back()->with('success', 'Kunde aktualisiert.');
    }

    public function claimPrimaryStaff(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('claimPrimaryStaff', $customer);

        $staffMember = StaffMember::query()
            ->where('company_id', $customer->company_id)
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->first();

        if (! $staffMember) {
            return back()->with('error', 'Kein Mitarbeiterprofil mit Ihrem Benutzer verknüpft.');
        }

        $validated = $request->validate([
            'action' => ['required', Rule::in(['claim', 'release'])],
        ]);

        if ($validated['action'] === 'claim') {
            $customer->update(['primary_staff_member_id' => $staffMember->id]);

            return back()->with('success', 'Sie sind jetzt Stammansprechpartner.');
        }

        if ((int) $customer->primary_staff_member_id !== (int) $staffMember->id) {
            return back()->with('error', 'Nur der aktuelle Stammansprechpartner kann die Zuordnung lösen.');
        }

        $customer->update(['primary_staff_member_id' => null]);

        return back()->with('success', 'Stammansprechpartner entfernt.');
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
