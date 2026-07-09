<?php

namespace App\Services\SchedulingSandbox;

use App\Models\AppointmentProposal;
use App\Services\AvailabilityService;
use App\Services\ClusteringService;
use Carbon\Carbon;

class SchedulingSandboxValidator
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly ClusteringService $clustering,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function validateProposals(AppointmentProposal $proposal): array
    {
        $proposal->load([
            'appointment.customer',
            'appointment.serviceType',
            'staffMember.serviceTypes',
        ]);

        $appointment = $proposal->appointment;
        $staff = $proposal->staffMember;
        $serviceType = $appointment->serviceType;
        $checks = [];

        $checks[] = $this->check(
            'qualification',
            'Mitarbeiter-Qualifikation',
            $staff !== null && $staff->serviceTypes->contains('id', $serviceType->id),
            $staff
                ? "Mitarbeiter „{$staff->name}“ ist für „{$serviceType->name}“ qualifiziert."
                : 'Kein Mitarbeiter zugewiesen.',
        );

        $region = $this->clustering->regionKey($appointment->customer->postal_code);
        $checks[] = $this->check(
            'region',
            'PLZ-Region',
            strlen($region) >= 3,
            "Kunden-PLZ {$appointment->customer->postal_code} → Region {$region}.",
        );

        foreach ($proposal->options() as $number => $slot) {
            if (! $slot || ! $staff) {
                $checks[] = $this->check("slot_{$number}", "Option {$number}", false, 'Slot oder Mitarbeiter fehlt.');

                continue;
            }

            $duration = $appointment->duration_minutes;
            $available = $this->availability
                ->getAvailableSlots($staff, Carbon::parse($slot), $duration)
                ->contains(fn ($availableSlot) => $availableSlot->start->copy()->startOfMinute()->equalTo(
                    Carbon::parse($slot)->startOfMinute()
                ));

            $checks[] = $this->check(
                "slot_{$number}_availability",
                "Option {$number}: Verfügbarkeit",
                $available,
                $available
                    ? "Slot {$slot->format('d.m.Y H:i')} liegt in der Arbeitszeit."
                    : "Slot {$slot->format('d.m.Y H:i')} liegt außerhalb der Verfügbarkeit oder kollidiert.",
            );
        }

        return $checks;
    }

    /**
     * @return array<string, mixed>
     */
    private function check(string $key, string $label, bool $passed, string $detail): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'passed' => $passed,
            'detail' => $detail,
        ];
    }
}
