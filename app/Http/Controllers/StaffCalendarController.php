<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\StaffMember;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StaffCalendarController extends Controller
{
    public function __invoke(Request $request, AvailabilityService $availabilityService): Response
    {
        $user = $request->user();
        $staffMember = StaffMember::where('user_id', $user->id)->first()
            ?? StaffMember::where('company_id', $user->company_id)->first();

        abort_unless($staffMember, 404, 'Kein Mitarbeiterprofil gefunden.');

        $date = Carbon::parse($request->get('date', today()->toDateString()));
        $duration = 60;

        $slots = $availabilityService->getAvailableSlots($staffMember, $date, $duration)
            ->map(fn ($slot) => [
                'start' => $slot->start->format('H:i'),
                'end' => $slot->end->format('H:i'),
            ]);

        $appointments = Appointment::with(['customer', 'serviceType'])
            ->where('staff_member_id', $staffMember->id)
            ->whereDate('scheduled_at', $date)
            ->whereIn('status', [AppointmentStatus::Confirmed, AppointmentStatus::Proposed])
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'customer' => $a->customer->name,
                'service' => $a->serviceType->name,
                'status' => $a->status->value,
                'time' => $a->scheduled_at?->format('H:i'),
            ]);

        return Inertia::render('Staff/Calendar', [
            'date' => $date->toDateString(),
            'staffMember' => ['id' => $staffMember->id, 'name' => $staffMember->name],
            'slots' => $slots,
            'appointments' => $appointments,
            'availabilities' => $staffMember->availabilities,
        ]);
    }
}
