<?php

namespace App\Services;

use App\DTOs\TimeSlot;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\StaffMember;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AvailabilityService
{
    /**
     * @return Collection<int, TimeSlot>
     */
    public function getAvailableSlots(StaffMember $staff, Carbon $date, int $durationMinutes): Collection
    {
        $windows = $this->getWorkingWindows($staff, $date);

        if ($windows->isEmpty()) {
            return collect();
        }

        $busyPeriods = $this->getBusyPeriods($staff, $date);
        $slots = collect();

        foreach ($windows as $window) {
            $cursor = $window['start']->copy();
            $windowEnd = $window['end'];

            while ($cursor->copy()->addMinutes($durationMinutes)->lte($windowEnd)) {
                $slotEnd = $cursor->copy()->addMinutes($durationMinutes);

                if (! $this->overlapsBusy($cursor, $slotEnd, $busyPeriods)) {
                    $slots->push(new TimeSlot($cursor->copy(), $slotEnd, $staff->id));
                }

                $cursor->addMinutes(15);
            }
        }

        return $slots;
    }

    /**
     * @return Collection<int, array{start: Carbon, end: Carbon}>
     */
    public function getWorkingWindows(StaffMember $staff, Carbon $date): Collection
    {
        $exception = $staff->availabilityExceptions()
            ->whereDate('date', $date->toDateString())
            ->first();

        if ($exception) {
            if (! $exception->is_available) {
                return collect();
            }

            return collect([[
                'start' => $date->copy()->setTimeFromTimeString($exception->start_time),
                'end' => $date->copy()->setTimeFromTimeString($exception->end_time),
            ]]);
        }

        $availability = $staff->availabilities()
            ->where('day_of_week', $date->dayOfWeek)
            ->first();

        if (! $availability) {
            return collect();
        }

        return collect([[
            'start' => $date->copy()->setTimeFromTimeString($availability->start_time),
            'end' => $date->copy()->setTimeFromTimeString($availability->end_time),
        ]]);
    }

    /**
     * @return Collection<int, array{start: Carbon, end: Carbon}>
     */
    public function getBusyPeriods(StaffMember $staff, Carbon $date): Collection
    {
        return Appointment::query()
            ->where('staff_member_id', $staff->id)
            ->whereDate('scheduled_at', $date->toDateString())
            ->whereIn('status', [
                AppointmentStatus::Confirmed,
                AppointmentStatus::Proposed,
            ])
            ->get()
            ->map(function (Appointment $appointment) use ($staff) {
                $start = $appointment->scheduled_at;
                $end = $start->copy()->addMinutes(
                    $appointment->duration_minutes + $appointment->travel_time_minutes + $staff->buffer_minutes
                );

                return ['start' => $start, 'end' => $end];
            });
    }

    /**
     * @param  Collection<int, array{start: Carbon, end: Carbon}>  $busyPeriods
     */
    private function overlapsBusy(Carbon $start, Carbon $end, Collection $busyPeriods): bool
    {
        return $busyPeriods->contains(function (array $busy) use ($start, $end) {
            return $start->lt($busy['end']) && $end->gt($busy['start']);
        });
    }

    /**
     * Token-efficient slot export for AI context.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function exportSlotsForAi(StaffMember $staff, Carbon $from, Carbon $to, int $durationMinutes): Collection
    {
        $slots = collect();
        $cursor = $from->copy()->startOfDay();

        while ($cursor->lte($to)) {
            $this->getAvailableSlots($staff, $cursor, $durationMinutes)
                ->take(12)
                ->each(fn (TimeSlot $slot) => $slots->push($slot->toArray()));

            $cursor->addDay();
        }

        return $slots;
    }
}
