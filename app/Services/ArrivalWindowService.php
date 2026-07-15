<?php

namespace App\Services;

use App\DTOs\ArrivalWindow;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentProposal;
use App\Models\Company;
use App\Models\StaffMember;
use Carbon\Carbon;

class ArrivalWindowService
{
    public function forSlot(
        Carbon $slotStart,
        StaffMember $staff,
        Company $company,
        ?int $excludeAppointmentId = null,
    ): ArrivalWindow {
        $priorCount = $this->priorAppointmentsCount($slotStart, $staff, $company, $excludeAppointmentId);
        $widthMinutes = $this->widthMinutes($priorCount);

        $start = $slotStart->copy();
        $end = $start->copy()->addMinutes($widthMinutes);

        return new ArrivalWindow($start, $end, $widthMinutes, $priorCount);
    }

    /**
     * @return array<int, ArrivalWindow>
     */
    public function forProposal(AppointmentProposal $proposal): array
    {
        $proposal->loadMissing(['appointment.company', 'staffMember']);
        $staff = $proposal->staffMember ?? $proposal->appointment->staffMember;

        if (! $staff) {
            return [];
        }

        $company = $proposal->appointment->company;
        $excludeId = $proposal->appointment_id;

        $windows = [];
        foreach ($proposal->options() as $number => $slot) {
            if (! $slot) {
                continue;
            }

            $windows[$number] = $this->forSlot($slot, $staff, $company, $excludeId);
        }

        return $windows;
    }

    public function formatLabel(ArrivalWindow $window, Company $company): string
    {
        $timezone = $company->timezone ?: config('app.timezone');
        $start = $window->start->copy()->timezone($timezone);
        $end = $window->end->copy()->timezone($timezone);

        $weekday = rtrim($start->locale('de')->translatedFormat('D'), '.');
        $datePart = $start->format('d.m.Y');

        return sprintf(
            '%s, %s · Ankunft %s–%s Uhr',
            $weekday,
            $datePart,
            $start->format('H:i'),
            $end->format('H:i'),
        );
    }

    public function formatOptionLabel(ArrivalWindow $window, Company $company, int $optionNumber, bool $recommended = false): string
    {
        $prefix = $recommended
            ? "Option {$optionNumber} (Empfohlener Termin)"
            : "Option {$optionNumber}";

        return "{$prefix}: {$this->formatLabel($window, $company)}";
    }

    private function priorAppointmentsCount(
        Carbon $slotStart,
        StaffMember $staff,
        Company $company,
        ?int $excludeAppointmentId,
    ): int {
        return Appointment::query()
            ->where('staff_member_id', $staff->id)
            ->where('company_id', $company->id)
            ->whereNotNull('scheduled_at')
            ->whereDate('scheduled_at', $slotStart->toDateString())
            ->where('scheduled_at', '<', $slotStart)
            ->whereNot('status', AppointmentStatus::Cancelled)
            ->whereIn('status', [AppointmentStatus::Confirmed, AppointmentStatus::Proposed])
            ->when($excludeAppointmentId, fn ($query) => $query->where('id', '!=', $excludeAppointmentId))
            ->count();
    }

    private function widthMinutes(int $priorAppointmentsCount): int
    {
        $base = config('scheduling.arrival_window_base_minutes', 30);
        $increment = config('scheduling.arrival_window_increment_minutes', 15);
        $max = config('scheduling.arrival_window_max_minutes', 90);

        return min($base + ($increment * $priorAppointmentsCount), $max);
    }
}
