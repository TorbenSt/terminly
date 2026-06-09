<?php

namespace App\Http\Controllers;

use App\Models\ServiceType;
use App\Models\StaffAvailability;
use App\Models\StaffMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StaffMemberController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', StaffMember::class);

        return Inertia::render('Staff/Index', [
            'staffMembers' => StaffMember::with(['serviceTypes', 'availabilities'])->orderBy('name')->get(),
            'serviceTypes' => ServiceType::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', StaffMember::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'buffer_minutes' => ['integer', 'min:0', 'max:120'],
            'service_type_ids' => ['array'],
            'service_type_ids.*' => ['integer', 'exists:service_types,id'],
        ]);

        $staff = StaffMember::create(collect($validated)->except('service_type_ids')->all());
        $staff->serviceTypes()->sync($validated['service_type_ids'] ?? []);

        foreach (range(1, 5) as $day) {
            StaffAvailability::create([
                'staff_member_id' => $staff->id,
                'day_of_week' => $day,
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
            ]);
        }

        return back()->with('success', 'Mitarbeiter angelegt.');
    }

    public function updateAvailability(Request $request, StaffMember $staffMember): RedirectResponse
    {
        $this->authorize('update', $staffMember);

        $validated = $request->validate([
            'availabilities' => ['required', 'array'],
            'availabilities.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'availabilities.*.start_time' => ['required', 'date_format:H:i'],
            'availabilities.*.end_time' => ['required', 'date_format:H:i'],
        ]);

        foreach ($validated['availabilities'] as $availability) {
            StaffAvailability::updateOrCreate(
                [
                    'staff_member_id' => $staffMember->id,
                    'day_of_week' => $availability['day_of_week'],
                ],
                [
                    'start_time' => $availability['start_time'].':00',
                    'end_time' => $availability['end_time'].':00',
                ]
            );
        }

        return back()->with('success', 'Verfügbarkeit gespeichert.');
    }
}
