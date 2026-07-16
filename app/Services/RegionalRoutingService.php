<?php

namespace App\Services;

use App\DTOs\TimeSlot;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\StaffMember;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RegionalRoutingService
{
    public function __construct(
        private readonly ClusteringService $clustering,
        private readonly AvailabilityService $availability,
    ) {}

    /**
     * Privacy-friendly export of existing tours for AI scheduling (production + sandbox).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function exportAppointmentsForAi(Company $company): Collection
    {
        $from = now()->startOfDay();
        $to = now()->addDays((int) config('scheduling.ai_appointment_horizon_days', 90))->endOfDay();

        return Appointment::query()
            ->where('company_id', $company->id)
            ->whereIn('status', [AppointmentStatus::Confirmed, AppointmentStatus::Proposed])
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$from, $to])
            ->with('customer:id,postal_code')
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (Appointment $appointment) => [
                'staff_id' => $appointment->staff_member_id,
                'date' => $appointment->scheduled_at->toDateString(),
                'time' => $appointment->scheduled_at->format('H:i'),
                'plz_prefix' => $this->clustering->regionKey($appointment->customer->postal_code),
                'duration_min' => $appointment->duration_minutes,
            ]);
    }

    public function regionKey(string $postalCode): string
    {
        return $this->clustering->regionKey($postalCode);
    }

    /**
     * PLZ regions already served by this staff member on the given day.
     *
     * @return list<string>
     */
    public function regionsOnDate(StaffMember $staff, Carbon $date): array
    {
        return Appointment::query()
            ->where('staff_member_id', $staff->id)
            ->whereDate('scheduled_at', $date->toDateString())
            ->whereIn('status', [AppointmentStatus::Confirmed, AppointmentStatus::Proposed])
            ->whereNotNull('scheduled_at')
            ->with('customer:id,postal_code')
            ->get()
            ->map(fn (Appointment $appointment) => $this->regionKey($appointment->customer->postal_code))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Same-day region lock: empty day or only the customer's region is allowed.
     * Days with a different PLZ tour (e.g. Frankfurt while customer is Berlin) are blocked.
     */
    public function isDayCompatible(StaffMember $staff, Carbon $date, string $customerRegionKey): bool
    {
        $regions = $this->regionsOnDate($staff, $date);

        if ($regions === []) {
            return true;
        }

        foreach ($regions as $region) {
            if ($region !== $customerRegionKey) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return Collection<int, array{date: Carbon, score: int}>
     */
    public function rankDatesByRegion(
        StaffMember $staff,
        string $regionKey,
        ?Carbon $from = null,
        ?Carbon $to = null,
    ): Collection {
        $from ??= now()->startOfDay();
        $to ??= now()->addDays((int) config('scheduling.ai_appointment_horizon_days', 90))->endOfDay();

        $dates = collect();
        $cursor = $from->copy()->startOfDay();

        while ($cursor->lte($to)) {
            if (! $this->isDayCompatible($staff, $cursor, $regionKey)) {
                $cursor->addDay();

                continue;
            }

            $dates->push([
                'date' => $cursor->copy(),
                'score' => $this->countRegionalAppointmentsOnDate($staff, $cursor, $regionKey),
            ]);
            $cursor->addDay();
        }

        return $dates
            ->sort(function (array $a, array $b) {
                if ($a['score'] !== $b['score']) {
                    return $b['score'] <=> $a['score'];
                }

                return $a['date']->timestamp <=> $b['date']->timestamp;
            })
            ->values();
    }

    public function bestRegionalDate(
        StaffMember $staff,
        string $postalCode,
        ?Carbon $from = null,
        ?Carbon $to = null,
    ): ?Carbon {
        $regionKey = $this->regionKey($postalCode);
        $ranked = $this->rankDatesByRegion($staff, $regionKey, $from, $to);

        $withTour = $ranked->first(fn (array $entry) => $entry['score'] > 0);

        return ($withTour ?? $ranked->first())['date'] ?? null;
    }

    /**
     * Build 3 diverse slots preferring days where the staff member already serves the customer's PLZ region.
     *
     * @return list<string>
     */
    public function buildRegionalSlots(
        StaffMember $staff,
        string $postalCode,
        int $durationMinutes,
        ?Carbon $earliestDate = null,
    ): array {
        $regionKey = $this->regionKey($postalCode);
        $from = $earliestDate?->copy()->startOfDay() ?? now()->startOfDay();
        $to = now()->addDays((int) config('scheduling.ai_appointment_horizon_days', 90))->endOfDay();
        $rankedDates = $this->rankDatesByRegion($staff, $regionKey, $from, $to);

        $selected = collect();
        $regionalDays = $rankedDates->filter(fn (array $entry) => $entry['score'] > 0)->values();
        // Empty / same-region-compatible days only (foreign tours already filtered out).
        $openDays = $rankedDates->filter(fn (array $entry) => $entry['score'] === 0)->values();

        $datesToTry = collect();
        foreach ($regionalDays as $entry) {
            $datesToTry->push($entry['date']);
        }

        if ($regionalDays->isNotEmpty()) {
            $datesToTry->push($regionalDays->first()['date']->copy()->addWeek());
        }

        if ($datesToTry->isEmpty()) {
            $datesToTry = $openDays->map(fn (array $entry) => $entry['date']);
        }

        foreach ($datesToTry as $date) {
            if ($selected->count() >= 3) {
                break;
            }

            if (! $this->isDayCompatible($staff, $date, $regionKey)) {
                continue;
            }

            $this->pickSlotsFromDate($staff, $date, $durationMinutes, $selected);
        }

        if ($selected->count() < 3) {
            foreach ($datesToTry as $date) {
                if ($selected->count() >= 3) {
                    break;
                }

                if (! $this->isDayCompatible($staff, $date, $regionKey)) {
                    continue;
                }

                $this->pickSlotsFromDate($staff, $date, $durationMinutes, $selected, force: true);
            }
        }

        if ($selected->count() < 3) {
            foreach ($openDays as $entry) {
                if ($selected->count() >= 3) {
                    break;
                }

                if (! $this->isDayCompatible($staff, $entry['date'], $regionKey)) {
                    continue;
                }

                $this->pickSlotsFromDate($staff, $entry['date'], $durationMinutes, $selected, force: true);
            }
        }

        if ($selected->count() < 3) {
            return $this->fallbackSlots($staff, $postalCode, $durationMinutes, $earliestDate);
        }

        return $this->sortIsoSlots(
            $selected->take(3)->map(fn (TimeSlot $slot) => $slot->start->toIso8601String())->all(),
        );
    }

    /**
     * @param  list<string>  $isoSlots
     * @return list<string>
     */
    public function countSlotsOnBestRegionalDate(
        StaffMember $staff,
        string $postalCode,
        array $isoSlots,
    ): int {
        $bestDate = $this->bestRegionalDate($staff, $postalCode);

        if (! $bestDate) {
            return 0;
        }

        return collect($isoSlots)
            ->filter(fn (string $iso) => Carbon::parse($iso)->toDateString() === $bestDate->toDateString())
            ->count();
    }

    /**
     * @param  Collection<int, TimeSlot>  $selected
     */
    private function pickSlotsFromDate(
        StaffMember $staff,
        Carbon $date,
        int $durationMinutes,
        Collection $selected,
        bool $force = false,
    ): void {
        $available = $this->availability->getAvailableSlots($staff, $date, $durationMinutes);

        foreach ($available as $slot) {
            if ($selected->count() >= 3) {
                return;
            }

            if ($selected->contains(fn (TimeSlot $existing) => $existing->start->eq($slot->start))) {
                continue;
            }

            if (! $force && $this->violatesSameDayGap($slot, $selected)) {
                continue;
            }

            $selected->push($slot);
        }
    }

    /**
     * @param  Collection<int, TimeSlot>  $selected
     */
    private function violatesSameDayGap(TimeSlot $candidate, Collection $selected): bool
    {
        foreach ($selected as $existing) {
            if (! $candidate->start->isSameDay($existing->start)) {
                continue;
            }

            if ($candidate->start->diffInMinutes($existing->start) < 120) {
                return true;
            }
        }

        return false;
    }

    private function countRegionalAppointmentsOnDate(
        StaffMember $staff,
        Carbon $date,
        string $regionKey,
    ): int {
        return Appointment::query()
            ->where('staff_member_id', $staff->id)
            ->whereDate('scheduled_at', $date->toDateString())
            ->whereIn('status', [AppointmentStatus::Confirmed, AppointmentStatus::Proposed])
            ->with('customer:id,postal_code')
            ->get()
            ->filter(fn (Appointment $appointment) => $this->regionKey($appointment->customer->postal_code) === $regionKey)
            ->count();
    }

    /**
     * @return list<string>
     */
    private function fallbackSlots(
        StaffMember $staff,
        string $postalCode,
        int $durationMinutes,
        ?Carbon $earliestDate,
    ): array {
        $regionKey = $this->regionKey($postalCode);
        $selected = collect();
        $cursor = ($earliestDate ?? now())->copy()->startOfDay();
        $limit = (int) config('scheduling.candidate_search_weekdays', 90);
        $scanned = 0;

        while ($scanned < $limit && $selected->count() < 3) {
            if ($cursor->isWeekday() && $this->isDayCompatible($staff, $cursor, $regionKey)) {
                $this->pickSlotsFromDate($staff, $cursor, $durationMinutes, $selected, force: true);
                $scanned++;
            } elseif ($cursor->isWeekday()) {
                $scanned++;
            }

            $cursor->addDay();
        }

        return $this->sortIsoSlots(
            $selected->take(3)->map(fn (TimeSlot $slot) => $slot->start->toIso8601String())->all(),
        );
    }

    /**
     * @param  list<string>  $isoSlots
     * @return list<string>
     */
    private function sortIsoSlots(array $isoSlots): array
    {
        return collect($isoSlots)
            ->map(fn (string $iso) => Carbon::parse($iso))
            ->sortBy(fn (Carbon $slot) => $slot->timestamp)
            ->map(fn (Carbon $slot) => $slot->toIso8601String())
            ->values()
            ->all();
    }
}
