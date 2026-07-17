<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentProposal;
use App\Models\Customer;
use App\Models\StaffMember;
use App\Services\AvailabilityService;
use App\Services\SchedulingSandbox\SchedulingSandboxService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SchedulingLabStaffCalendarController extends Controller
{
    public function __construct(
        private readonly SchedulingSandboxService $sandbox,
        private readonly AvailabilityService $availabilityService,
    ) {}

    public function __invoke(Request $request, StaffMember $staffMember): Response
    {
        return $this->show($request, $staffMember);
    }

    public function show(Request $request, StaffMember $staffMember): Response
    {
        $run = $this->sandbox->activeRunFor($request->user());

        abort_unless($run, 404, 'Kein aktiver Sandbox-Lauf.');
        abort_unless(
            $staffMember->company_id === $run->company_id && $run->company->isSandbox(),
            404,
            'Mitarbeiter gehört nicht zur aktiven Sandbox.',
        );

        $staffMember->load('serviceTypes:id,name');

        $date = Carbon::parse($request->get('date', today()->toDateString()));
        $duration = 60;

        $slots = $this->availabilityService
            ->getAvailableSlots($staffMember, $date, $duration)
            ->map(fn ($slot) => [
                'start' => $slot->start->format('H:i'),
                'end' => $slot->end->format('H:i'),
            ]);

        $appointments = Appointment::query()
            ->with(['customer:id,postal_code', 'serviceType:id,name'])
            ->where('staff_member_id', $staffMember->id)
            ->where('company_id', $run->company_id)
            ->whereDate('scheduled_at', $date)
            ->whereNot('status', AppointmentStatus::Cancelled)
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (Appointment $appointment) => [
                'id' => $appointment->id,
                'postal_code' => $appointment->customer->postal_code,
                'service' => $appointment->serviceType->name,
                'status' => $appointment->status->value,
                'status_label' => $appointment->status->label(),
                'time' => $appointment->scheduled_at?->format('H:i'),
            ]);

        $proposalOptions = AppointmentProposal::query()
            ->with(['appointment.customer:id,postal_code', 'appointment.serviceType:id,name'])
            ->where('staff_member_id', $staffMember->id)
            ->whereNull('responded_at')
            ->whereHas('appointment', fn ($query) => $query->where('company_id', $run->company_id))
            ->latest('id')
            ->get()
            ->flatMap(function (AppointmentProposal $proposal) use ($date) {
                $proposal->loadMissing(['appointment.company', 'staffMember']);
                $windows = app(\App\Services\ArrivalWindowService::class)->forProposal($proposal);
                $formatter = app(\App\Services\ArrivalWindowService::class);

                return collect($proposal->options())
                    ->filter(fn (?Carbon $slot) => $slot?->toDateString() === $date->toDateString())
                    ->map(function (Carbon $slot, int $option) use ($proposal, $windows, $formatter) {
                        $windowLabel = isset($windows[$option])
                            ? $formatter->formatLabel($windows[$option], $proposal->appointment->company)
                            : $slot->format('H:i');

                        return [
                            'id' => "{$proposal->id}-{$option}",
                            'proposal_id' => $proposal->id,
                            'option' => $option,
                            'round' => $proposal->round,
                            'postal_code' => $proposal->appointment->customer->postal_code,
                            'service' => $proposal->appointment->serviceType->name,
                            'status' => 'proposal_option',
                            'status_label' => "Vorschlag Option {$option}",
                            'time' => $windowLabel,
                        ];
                    });
            })
            ->sortBy('time')
            ->values();

        $appointmentDates = Appointment::query()
            ->where('staff_member_id', $staffMember->id)
            ->where('company_id', $run->company_id)
            ->whereNotNull('scheduled_at')
            ->whereNot('status', AppointmentStatus::Cancelled)
            ->pluck('scheduled_at')
            ->map(fn ($scheduledAt) => Carbon::parse($scheduledAt)->toDateString());

        $proposalDates = AppointmentProposal::query()
            ->where('staff_member_id', $staffMember->id)
            ->whereNull('responded_at')
            ->whereHas('appointment', fn ($query) => $query->where('company_id', $run->company_id))
            ->get()
            ->flatMap(fn (AppointmentProposal $proposal) => collect($proposal->options())
                ->filter()
                ->map(fn (Carbon $slot) => $slot->toDateString()));

        $appointmentDates = $appointmentDates
            ->merge($proposalDates)
            ->unique()
            ->values()
            ->all();

        return Inertia::render('Admin/SchedulingLab/StaffCalendar', [
            'date' => $date->toDateString(),
            'appointmentDates' => $appointmentDates,
            'run' => [
                'company_name' => $run->company->name,
                'scenario_label' => $run->scenario?->label(),
            ],
            'staffMember' => [
                'id' => $staffMember->id,
                'name' => $staffMember->name,
                'services' => $staffMember->serviceTypes->pluck('name'),
                'stamm_customers' => Customer::query()
                    ->where('company_id', $staffMember->company_id)
                    ->where('primary_staff_member_id', $staffMember->id)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (Customer $customer) => [
                        'id' => $customer->id,
                        'name' => $customer->name,
                    ])
                    ->values()
                    ->all(),
            ],
            'slots' => $slots,
            'appointments' => $appointments,
            'proposalOptions' => $proposalOptions,
        ]);
    }
}
