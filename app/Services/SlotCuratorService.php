<?php

namespace App\Services;

use App\DTOs\TimeSlot;
use App\Models\StaffMember;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SlotCuratorService
{
    private const MIN_SAME_DAY_GAP_HOURS = 2;

    public function __construct(
        private readonly AvailabilityService $availabilityService,
        private readonly RegionalRoutingService $regionalRouting,
    ) {}

    /**
     * Curate exactly 3 diverse negotiation slots from staff availability.
     *
     * @return array{slots: list<string>, recommended_index: int}
     */
    public function curate(
        StaffMember $staff,
        string $feedback,
        int $durationMinutes,
        ?string $customerPostalCode = null,
    ): array {
        $preferences = $this->parseFeedback($feedback);
        $candidates = $this->collectCandidates($staff, $preferences, $durationMinutes, $customerPostalCode);

        return $this->selectSlots($candidates, $preferences, $feedback);
    }

    /**
     * Re-curate Grok (or other) slot suggestions using the same diversity rules.
     *
     * @param  list<string>  $isoSlots
     * @return array{slots: list<string>, recommended_index: int}
     */
    public function curateFromIsoSlots(
        StaffMember $staff,
        array $isoSlots,
        string $feedback,
        int $durationMinutes,
        ?string $customerPostalCode = null,
    ): array {
        $preferences = $this->parseFeedback($feedback);
        $candidates = $this->collectCandidates($staff, $preferences, $durationMinutes, $customerPostalCode);

        $customerRegion = $customerPostalCode
            ? $this->regionalRouting->regionKey($customerPostalCode)
            : null;

        foreach ($isoSlots as $index => $iso) {
            $start = Carbon::parse($iso);

            if (! $this->matchesConstraints($start, $preferences)) {
                continue;
            }

            if (! $this->matchesPreferredWeekday($start, $preferences)) {
                continue;
            }

            if ($customerRegion && ! $this->regionalRouting->isDayCompatible($staff, $start, $customerRegion)) {
                continue;
            }

            $slot = new TimeSlot($start, $start->copy()->addMinutes($durationMinutes), $staff->id);
            $tier = $index === 0 ? 0 : 1;
            $candidates->push($this->tagCandidate($slot, $tier, $preferences, $staff, $customerRegion));
        }

        $candidates = $candidates
            ->unique(fn (array $c) => $c['slot']->start->toIso8601String())
            ->values();

        return $this->selectSlots($candidates, $preferences, $feedback);
    }

    /**
     * @return array{
     *     preferred_day: ?int,
     *     preferred_days: list<int>,
     *     time_of_day: string,
     *     week_offset: int,
     *     earliest_date: ?Carbon,
     *     latest_date: ?Carbon,
     *     earliest_hour: ?int,
     * }
     */
    public function parseFeedback(string $feedback): array
    {
        $lower = mb_strtolower($feedback);

        $dayMap = [
            'montag' => Carbon::MONDAY,
            'dienstag' => Carbon::TUESDAY,
            'mittwoch' => Carbon::WEDNESDAY,
            'donnerstag' => Carbon::THURSDAY,
            'freitag' => Carbon::FRIDAY,
        ];

        $preferredDays = [];
        foreach ($dayMap as $name => $dayOfWeek) {
            if (str_contains($lower, $name)) {
                $preferredDays[] = $dayOfWeek;
            }
        }
        $preferredDays = array_values(array_unique($preferredDays));

        $timeOfDay = 'any';
        if (str_contains($lower, 'nachmittag') || str_contains($lower, 'nachmittags')) {
            $timeOfDay = 'afternoon';
        } elseif (
            str_contains($lower, 'vormittag')
            || str_contains($lower, 'vormittags')
            || str_contains($lower, 'morgens')
            || preg_match('/\bmorgen\b/u', $lower)
        ) {
            $timeOfDay = 'morning';
        }

        $weekOffset = 1;
        if (preg_match('/in\s+(?:einer?|1)\s+woche/u', $lower)) {
            $weekOffset = 1;
        } elseif (preg_match('/in\s+(\d+)\s+wochen?/u', $lower, $matches)) {
            $weekOffset = max(1, (int) $matches[1]);
        } elseif (str_contains($lower, 'nächste woche') || str_contains($lower, 'naechste woche')) {
            $weekOffset = 1;
        }

        $dateConstraints = $this->parseDateConstraints($lower);

        return [
            'preferred_day' => $preferredDays[0] ?? null,
            'preferred_days' => $preferredDays,
            'time_of_day' => $timeOfDay,
            'week_offset' => $weekOffset,
            'earliest_date' => $dateConstraints['earliest_date'],
            'latest_date' => $dateConstraints['latest_date'],
            'earliest_hour' => $this->parseEarliestHour($lower),
        ];
    }

    private function parseEarliestHour(string $lower): ?int
    {
        if (preg_match('/(?:ab|nach)\s+(\d{1,2})(?::(\d{2}))?\s*uhr/u', $lower, $matches)) {
            return min(23, max(0, (int) $matches[1]));
        }

        return null;
    }

    /**
     * @return array{earliest_date: ?Carbon, latest_date: ?Carbon}
     */
    private function parseDateConstraints(string $lower): array
    {
        $explicitEarliest = $this->parseExplicitEarliestDate($lower);

        if ($explicitEarliest !== null) {
            return [
                'earliest_date' => $explicitEarliest,
                'latest_date' => null,
            ];
        }

        $monthPeriod = $this->parseMonthPeriod($lower);

        if ($monthPeriod !== null) {
            return $monthPeriod;
        }

        return [
            'earliest_date' => null,
            'latest_date' => null,
        ];
    }

    /**
     * @return array{earliest_date: Carbon, latest_date: Carbon}|null
     */
    private function parseMonthPeriod(string $lower): ?array
    {
        $months = $this->monthNameMap();
        $monthPattern = implode('|', array_keys($months));

        $period = null;
        $monthName = null;

        if (preg_match(
            '/(?:lieber\s+|gerne\s+|am\s+besten\s+)?(?:anfang|beginn|früh(?:\s+im)?)\s*(?:des?\s+|im\s+)?('.$monthPattern.')/u',
            $lower,
            $matches,
        )) {
            $period = 'start';
            $monthName = $matches[1];
        } elseif (preg_match(
            '/(?:lieber\s+|gerne\s+|am\s+besten\s+)?(?:mitte|mitten\s+im)\s*(?:des?\s+)?('.$monthPattern.')/u',
            $lower,
            $matches,
        )) {
            $period = 'mid';
            $monthName = $matches[1];
        } elseif (preg_match(
            '/(?:lieber\s+|gerne\s+|am\s+besten\s+)?(?:ende|enden|spät\s+im|gegen\s+ende)\s*(?:des?\s+|im\s+)?('.$monthPattern.')/u',
            $lower,
            $matches,
        )) {
            $period = 'end';
            $monthName = $matches[1];
        } elseif (preg_match('/\b('.$monthPattern.')\s+(?:ende|enden)\b/u', $lower, $matches)) {
            $period = 'end';
            $monthName = $matches[1];
        } elseif (preg_match(
            '/(?:lieber\s+|gerne\s+|am\s+besten\s+|im\s+|in\s+)(?:dem\s+)?('.$monthPattern.')\b/u',
            $lower,
            $matches,
        )) {
            $period = 'full';
            $monthName = $matches[1];
        }

        if ($period === null || $monthName === null) {
            return null;
        }

        $month = $months[$monthName] ?? null;

        if ($month === null) {
            return null;
        }

        return $this->monthPeriodWindow($month, $period);
    }

    /**
     * @return array<string, int>
     */
    private function monthNameMap(): array
    {
        return [
            'januar' => 1,
            'februar' => 2,
            'märz' => 3,
            'maerz' => 3,
            'april' => 4,
            'mai' => 5,
            'juni' => 6,
            'juli' => 7,
            'august' => 8,
            'september' => 9,
            'oktober' => 10,
            'november' => 11,
            'dezember' => 12,
        ];
    }

    /**
     * @return array{earliest_date: Carbon, latest_date: Carbon}
     */
    private function monthPeriodWindow(int $month, string $period): array
    {
        $year = now()->year;
        $daysInMonth = Carbon::create($year, $month, 1, 0, 0, 0, config('app.timezone'))->daysInMonth;

        [$startDay, $endDay] = match ($period) {
            'start' => [1, min(10, $daysInMonth)],
            'mid' => [11, min(20, $daysInMonth)],
            'end' => [max(1, $daysInMonth - 9), $daysInMonth],
            default => [1, $daysInMonth],
        };

        $earliest = $this->resolveEarliestDate($startDay, $month, $year);
        $latest = $this->resolveEarliestDate($endDay, $month, $year);

        if ($latest->lt(now()->startOfDay()) && $earliest->lt(now()->startOfDay())) {
            $year++;
            $daysInMonth = Carbon::create($year, $month, 1, 0, 0, 0, config('app.timezone'))->daysInMonth;
            [$startDay, $endDay] = match ($period) {
                'start' => [1, min(10, $daysInMonth)],
                'mid' => [11, min(20, $daysInMonth)],
                'end' => [max(1, $daysInMonth - 9), $daysInMonth],
                default => [1, $daysInMonth],
            };
            $earliest = $this->resolveEarliestDate($startDay, $month, $year);
            $latest = $this->resolveEarliestDate($endDay, $month, $year);
        }

        return [
            'earliest_date' => $earliest,
            'latest_date' => $latest,
        ];
    }

    private function parseExplicitEarliestDate(string $lower): ?Carbon
    {
        $months = $this->monthNameMap();

        $ordinals = [
            'ersten' => 1,
            'erste' => 1,
            'zweiten' => 2,
            'zweite' => 2,
            'dritten' => 3,
            'dritte' => 3,
            'vierten' => 4,
            'vierte' => 4,
            'fünften' => 5,
            'funften' => 5,
            'fünfte' => 5,
            'funfte' => 5,
        ];

        if (preg_match(
            '/(?:ab|erst ab)\s+(?:dem\s+)?(?:(\d{1,2})\.|(\w+))\s*(januar|februar|märz|maerz|april|mai|juni|juli|august|september|oktober|november|dezember)/u',
            $lower,
            $matches,
        )) {
            $day = filled($matches[1] ?? null)
                ? (int) $matches[1]
                : ($ordinals[$matches[2]] ?? null);
            $month = $months[$matches[3]] ?? null;

            if ($day && $month) {
                return $this->resolveEarliestDate($day, $month);
            }
        }

        if (preg_match('/(?:ab|erst ab)\s+(?:dem\s+)?(\d{1,2})\.(\d{1,2})(?:\.(\d{4}))?/u', $lower, $matches)) {
            $day = (int) $matches[1];
            $month = (int) $matches[2];
            $year = isset($matches[3]) ? (int) $matches[3] : now()->year;

            return $this->resolveEarliestDate($day, $month, $year);
        }

        return null;
    }

    private function resolveEarliestDate(int $day, int $month, ?int $year = null): Carbon
    {
        $year ??= now()->year;
        $date = Carbon::create($year, $month, $day, 0, 0, 0, config('app.timezone'))->startOfDay();

        if ($date->lt(now()->startOfDay())) {
            $date->addYear();
        }

        return $date;
    }

    /**
     * @param  array{earliest_date: ?Carbon, latest_date: ?Carbon}  $preferences
     * @return list<Carbon>
     */
    private function datesInPreferenceWindow(array $preferences): array
    {
        if ($preferences['earliest_date'] === null || $preferences['latest_date'] === null) {
            return [];
        }

        $dates = [];
        $cursor = $preferences['earliest_date']->copy()->startOfDay();

        while ($cursor->lte($preferences['latest_date'])) {
            $dates[] = $cursor->copy();
            $cursor->addDay();
        }

        return $dates;
    }

    private function matchesConstraints(Carbon $time, array $preferences): bool
    {
        if (! $this->availabilityService->isBookableDate($time)) {
            return false;
        }

        if ($preferences['earliest_date'] !== null && $time->lt($preferences['earliest_date'])) {
            return false;
        }

        if ($preferences['latest_date'] !== null && $time->copy()->startOfDay()->gt($preferences['latest_date'])) {
            return false;
        }

        return $this->matchesTimeOfDay($time, $preferences);
    }

    /**
     * @param  array{preferred_day: ?int, time_of_day: string, week_offset: int}  $preferences
     * @return Collection<int, array{slot: TimeSlot, tier: int, score: int}>
     */
    private function collectCandidates(
        StaffMember $staff,
        array $preferences,
        int $durationMinutes,
        ?string $customerPostalCode = null,
    ): Collection {
        $preferredDate = $this->resolvePreferredDate($preferences);
        $candidates = collect();
        $windowDates = $this->datesInPreferenceWindow($preferences);
        $customerRegion = $customerPostalCode
            ? $this->regionalRouting->regionKey($customerPostalCode)
            : null;

        $rankedRegional = $customerRegion
            ? $this->regionalRouting->rankDatesByRegion(
                $staff,
                $customerRegion,
                $preferences['earliest_date'] ?? now()->startOfDay(),
                $preferences['latest_date']?->copy()->endOfDay(),
            )
            : collect();

        $regionalDates = $rankedRegional
            ->filter(fn (array $entry) => $entry['score'] > 0)
            ->when(
                ! empty($preferences['preferred_days']),
                fn (Collection $dates) => $dates->filter(
                    fn (array $entry) => $this->matchesPreferredWeekday($entry['date'], $preferences),
                ),
            )
            ->take(8)
            ->map(fn (array $entry) => $entry['date'])
            ->values()
            ->all();

        if (! empty($windowDates)) {
            $compatibleWindow = $customerRegion
                ? array_values(array_filter(
                    $windowDates,
                    fn (Carbon $date) => $this->regionalRouting->isDayCompatible($staff, $date, $customerRegion)
                        && $this->matchesPreferredWeekday($date, $preferences),
                ))
                : array_values(array_filter(
                    $windowDates,
                    fn (Carbon $date) => $this->matchesPreferredWeekday($date, $preferences),
                ));

            $sameRegionInWindow = array_values(array_filter(
                $compatibleWindow,
                fn (Carbon $date) => $customerRegion
                    && in_array($date->toDateString(), array_map(
                        fn (Carbon $regionalDate) => $regionalDate->toDateString(),
                        $regionalDates,
                    ), true),
            ));

            $tiers = [
                1 => ! empty($sameRegionInWindow) ? $sameRegionInWindow : array_slice($compatibleWindow, 0, 12),
                2 => $compatibleWindow,
                3 => $this->horizonSearchDates($preferredDate, $preferences, $staff, $customerRegion),
            ];
        } else {
            $tiers = [
                1 => ! empty($regionalDates) ? $regionalDates : [$preferredDate],
                2 => [$preferredDate->copy()->addWeek()],
                3 => [$preferredDate->copy()->addWeekday(), $preferredDate->copy()->addWeeks(2)],
                4 => $this->relaxedDates($preferredDate, $preferences),
                5 => $this->horizonSearchDates($preferredDate, $preferences, $staff, $customerRegion),
            ];
        }

        foreach ($tiers as $tier => $dates) {
            foreach ($dates as $date) {
                if (! $this->availabilityService->isBookableDate($date)) {
                    continue;
                }

                if ($preferences['earliest_date'] !== null && $date->lt($preferences['earliest_date'])) {
                    continue;
                }

                if ($preferences['latest_date'] !== null && $date->gt($preferences['latest_date'])) {
                    continue;
                }

                if ($customerRegion && ! $this->regionalRouting->isDayCompatible($staff, $date, $customerRegion)) {
                    continue;
                }

                if (! $this->matchesPreferredWeekday($date, $preferences)) {
                    continue;
                }

                $slots = $this->filterByTimeOfDay(
                    $this->availabilityService->getBookableSlots($staff, $date, $durationMinutes),
                    $preferences,
                );

                foreach ($slots as $slot) {
                    if (! $this->matchesConstraints($slot->start, $preferences)) {
                        continue;
                    }

                    $candidates->push($this->tagCandidate($slot, $tier, $preferences, $staff, $customerRegion));
                }
            }

            // Skip expensive horizon sweep when nearer tiers already yield a healthy pool.
            if ($tier >= 4 && $candidates->count() >= 24) {
                break;
            }
        }

        return $candidates
            ->unique(fn (array $c) => $c['slot']->start->toIso8601String())
            ->sortByDesc('score')
            ->values();
    }

    /**
     * Broader weekday sweep for dense calendars (real-life capacity scenarios).
     *
     * @param  array{earliest_date: ?Carbon, latest_date: ?Carbon, preferred_days: list<int>}  $preferences
     * @return list<Carbon>
     */
    private function horizonSearchDates(
        Carbon $preferredDate,
        array $preferences,
        ?StaffMember $staff = null,
        ?string $customerRegion = null,
    ): array {
        $limit = (int) config('scheduling.candidate_search_weekdays', 90);
        $dates = [];
        $cursor = ($preferences['earliest_date'] ?? $preferredDate)->copy()->startOfDay();
        $earliestBookable = $this->availabilityService->earliestBookableDate();

        if ($cursor->lt($earliestBookable)) {
            $cursor = $earliestBookable->copy();
        }

        if ($cursor->lt(now()->startOfDay())) {
            $cursor = now()->startOfDay();
        }

        $scanned = 0;
        while ($scanned < $limit) {
            if ($preferences['latest_date'] !== null && $cursor->gt($preferences['latest_date'])) {
                break;
            }

            if (
                $cursor->isWeekday()
                && $this->matchesPreferredWeekday($cursor, $preferences)
                && (
                    ! $staff
                    || ! $customerRegion
                    || $this->regionalRouting->isDayCompatible($staff, $cursor, $customerRegion)
                )
            ) {
                $dates[] = $cursor->copy();
                $scanned++;
            } elseif ($cursor->isWeekday()) {
                $scanned++;
            }

            $cursor->addDay();
        }

        return $dates;
    }

    /**
     * @param  array{preferred_days?: list<int>, preferred_day?: ?int}  $preferences
     */
    private function matchesPreferredWeekday(Carbon $date, array $preferences): bool
    {
        $days = $preferences['preferred_days'] ?? [];

        if ($days === [] && ($preferences['preferred_day'] ?? null) !== null) {
            $days = [$preferences['preferred_day']];
        }

        if ($days === []) {
            return true;
        }

        return in_array($date->dayOfWeek, $days, true);
    }

    /**
     * @param  Collection<int, array{slot: TimeSlot, tier: int, score: int}>  $candidates
     * @param  array{preferred_day: ?int, time_of_day: string, week_offset: int}  $preferences
     * @return array{slots: list<string>, recommended_index: int}
     */
    private function selectSlots(Collection $candidates, array $preferences, string $feedback): array
    {
        if ($candidates->isEmpty()) {
            return $this->finalizeSlots($this->fallbackSlots($preferences), 0);
        }

        $pool = $candidates->values();
        $selected = collect();

        foreach ($pool as $candidate) {
            if ($selected->count() >= 3) {
                break;
            }

            if ($this->violatesPairwiseGap($candidate['slot'], $selected, $feedback)) {
                continue;
            }

            if (! $this->matchesConstraints($candidate['slot']->start, $preferences)) {
                continue;
            }

            $selected->push($candidate);
        }

        while ($selected->count() < 3) {
            $extra = $pool->first(function (array $c) use ($selected, $feedback) {
                if ($selected->contains(fn (array $s) => $s['slot']->start->eq($c['slot']->start))) {
                    return false;
                }

                return ! $this->violatesPairwiseGap($c['slot'], $selected, $feedback);
            });

            if (! $extra) {
                break;
            }

            $selected->push($extra);
        }

        $recommendedIso = $selected->first()['slot']->start->toIso8601String();
        $isoSlots = $selected
            ->take(3)
            ->map(fn (array $c) => $c['slot']->start->toIso8601String())
            ->values()
            ->all();

        $isoSlots = $this->enforceHardRules($isoSlots, $candidates, $preferences, $feedback, $recommendedIso);

        while (count($isoSlots) < 3) {
            foreach ($this->fallbackSlots($preferences) as $fallback) {
                if (count($isoSlots) >= 3) {
                    break;
                }

                $fallbackSlot = new TimeSlot(
                    Carbon::parse($fallback),
                    Carbon::parse($fallback)->copy()->addHour(),
                    0,
                );

                if (in_array($fallback, $isoSlots, true)) {
                    continue;
                }

                if ($this->violatesPairwiseGap($fallbackSlot, $selected, $feedback)) {
                    continue;
                }

                $isoSlots[] = $fallback;
                $selected->push(['slot' => $fallbackSlot, 'tier' => 99, 'score' => 0]);
            }

            if (count($isoSlots) < 3) {
                break;
            }
        }

        return $this->finalizeSlots(array_slice($isoSlots, 0, 3), $recommendedIso);
    }

    /**
     * @param  list<string>  $slots
     * @return array{slots: list<string>, recommended_index: int}
     */
    private function finalizeSlots(array $slots, string|int $recommended): array
    {
        $recommendedIso = is_int($recommended)
            ? ($slots[$recommended] ?? $slots[0] ?? null)
            : $recommended;

        $sorted = collect($slots)
            ->map(fn (string $iso) => Carbon::parse($iso))
            ->sortBy(fn (Carbon $slot) => $slot->timestamp)
            ->values();

        $sortedIso = $sorted->map(fn (Carbon $slot) => $slot->toIso8601String())->all();
        $recommendedIndex = 0;

        if ($recommendedIso !== null) {
            $index = array_search($recommendedIso, $sortedIso, true);
            $recommendedIndex = $index === false ? 0 : $index;
        }

        return [
            'slots' => $sortedIso,
            'recommended_index' => $recommendedIndex,
        ];
    }

    /**
     * @param  Collection<int, array{slot: TimeSlot, tier: int, score: int}>  $selected
     */
    private function violatesPairwiseGap(TimeSlot $candidate, Collection $selected, string $feedback): bool
    {
        if ($this->isVagueSameDayRequest($feedback)) {
            return false;
        }

        foreach ($selected as $existing) {
            if (! $candidate->start->isSameDay($existing['slot']->start)) {
                continue;
            }

            if ($candidate->start->diffInMinutes($existing['slot']->start) < self::MIN_SAME_DAY_GAP_HOURS * 60) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $slots
     * @param  Collection<int, array{slot: TimeSlot, tier: int, score: int}>  $candidates
     * @param  array{preferred_day: ?int, time_of_day: string, week_offset: int}  $preferences
     * @return list<string>
     */
    private function enforceHardRules(
        array $slots,
        Collection $candidates,
        array $preferences,
        string $feedback,
        string $recommendedIso,
    ): array {
        $parsed = collect($slots)->map(fn (string $iso) => Carbon::parse($iso));

        if ($this->hasPairwiseSameDayViolations($parsed, $feedback)) {
            $slots = $this->replaceViolatingSlots($slots, $candidates, $feedback);
        }

        $parsed = collect($slots)->map(fn (string $iso) => Carbon::parse($iso));

        if ($this->areConsecutiveQuarterHourSlots($parsed)) {
            $slots = $this->replaceViolatingSlots($slots, $candidates, $feedback);
        }

        $parsed = collect($slots)->map(fn (string $iso) => Carbon::parse($iso));

        if ($this->isNarrowPreference($preferences) && $parsed->map->toDateString()->unique()->count() < 2) {
            $selected = $parsed->map(fn (Carbon $slot) => [
                'slot' => new TimeSlot($slot, $slot->copy()->addHour(), 0),
                'tier' => 0,
                'score' => 0,
            ]);

            $alternate = $candidates->first(function (array $c) use ($parsed, $selected, $feedback) {
                $date = $c['slot']->start->toDateString();

                if ($parsed->contains(fn (Carbon $p) => $p->toDateString() === $date)) {
                    return false;
                }

                return ! $this->violatesPairwiseGap($c['slot'], $selected, $feedback);
            });

            if ($alternate) {
                $replaceIndex = $this->findReplaceableSlotIndex($slots, $recommendedIso);
                $slots[$replaceIndex] = $alternate['slot']->start->toIso8601String();
            }
        }

        return array_values($slots);
    }

    /**
     * @param  list<string>  $slots
     */
    private function findReplaceableSlotIndex(array $slots, string $recommendedIso): int
    {
        foreach ($slots as $index => $iso) {
            if ($iso !== $recommendedIso) {
                return $index;
            }
        }

        return count($slots) - 1;
    }

    /**
     * @param  Collection<int, Carbon>  $slots
     */
    private function hasPairwiseSameDayViolations(Collection $slots, string $feedback): bool
    {
        if ($this->isVagueSameDayRequest($feedback)) {
            return false;
        }

        $values = $slots->values();

        for ($i = 0; $i < $values->count(); $i++) {
            for ($j = $i + 1; $j < $values->count(); $j++) {
                $a = $values[$i];
                $b = $values[$j];

                if ($a->isSameDay($b) && $a->diffInMinutes($b) < self::MIN_SAME_DAY_GAP_HOURS * 60) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $slots
     * @param  Collection<int, array{slot: TimeSlot, tier: int, score: int}>  $candidates
     * @return list<string>
     */
    private function replaceViolatingSlots(array $slots, Collection $candidates, string $feedback): array
    {
        $parsed = collect($slots)->map(fn (string $iso) => Carbon::parse($iso));

        foreach ($parsed->keys() as $index) {
            if ($index === 0) {
                continue;
            }

            $current = $parsed[$index];
            $others = $parsed->except($index);
            $tooClose = $others->contains(function (Carbon $other) use ($current, $feedback) {
                if ($this->isVagueSameDayRequest($feedback)) {
                    return false;
                }

                return $current->isSameDay($other)
                    && $current->diffInMinutes($other) < self::MIN_SAME_DAY_GAP_HOURS * 60;
            });

            if (! $tooClose) {
                continue;
            }

            $selected = $parsed->map(fn (Carbon $slot) => [
                'slot' => new TimeSlot($slot, $slot->copy()->addHour(), 0),
                'tier' => 0,
                'score' => 0,
            ]);

            $replacement = $candidates->first(function (array $c) use ($parsed, $index, $selected, $feedback) {
                $start = $c['slot']->start;

                if ($parsed->contains(fn (Carbon $p, int $i) => $i !== $index && $p->eq($start))) {
                    return false;
                }

                return ! $this->violatesPairwiseGap($c['slot'], $selected, $feedback);
            });

            if ($replacement) {
                $slots[$index] = $replacement['slot']->start->toIso8601String();
                $parsed[$index] = $replacement['slot']->start;
            }
        }

        return $slots;
    }

    /**
     * @param  Collection<int, Carbon>  $slots
     */
    private function areConsecutiveQuarterHourSlots(Collection $slots): bool
    {
        if ($slots->count() < 3) {
            return false;
        }

        $sorted = $slots->sortBy(fn (Carbon $s) => $s->timestamp)->values();

        return abs($sorted[0]->diffInMinutes($sorted[1])) === 15
            && abs($sorted[1]->diffInMinutes($sorted[2])) === 15
            && $sorted[0]->isSameDay($sorted[2]);
    }

    /**
     * @param  array{preferred_day: ?int, preferred_days?: list<int>, time_of_day: string, week_offset: int}  $preferences
     */
    private function tagCandidate(
        TimeSlot $slot,
        int $tier,
        array $preferences,
        ?StaffMember $staff = null,
        ?string $customerRegion = null,
    ): array {
        $score = match ($tier) {
            0 => 100,
            default => 40 - min($tier, 4),
        };

        if ($this->matchesPreferredWeekday($slot->start, $preferences)) {
            $score += 20;
        }

        if ($this->matchesTimeOfDay($slot->start, $preferences)) {
            $score += 10;
        }

        if ($preferences['earliest_date'] !== null && $slot->start->gte($preferences['earliest_date'])) {
            $score += 15;
        }

        if ($preferences['latest_date'] !== null && $slot->start->copy()->startOfDay()->lte($preferences['latest_date'])) {
            $score += 10;
            $score += max(0, 5 - $preferences['latest_date']->diffInDays($slot->start->copy()->startOfDay()));
        }

        if ($preferences['earliest_hour'] !== null && $slot->start->hour >= $preferences['earliest_hour']) {
            $score += 10;
        }

        if ($staff && $customerRegion) {
            $sameRegionCount = collect($this->regionalRouting->regionsOnDate($staff, $slot->start))
                ->filter(fn (string $region) => $region === $customerRegion)
                ->count();

            if ($sameRegionCount > 0) {
                $score += 40 + min(20, $sameRegionCount * 5);
            }
        }

        $preferredDate = $this->resolvePreferredDate($preferences);
        $weeksDiff = abs($preferredDate->diffInWeeks($slot->start->copy()->startOfDay()));
        $score += max(0, 8 - $weeksDiff);

        return [
            'slot' => $slot,
            'tier' => $tier,
            'score' => $score,
        ];
    }

    /**
     * @param  Collection<int, TimeSlot>  $slots
     * @param  array{preferred_day: ?int, time_of_day: string, week_offset: int}  $preferences
     * @return Collection<int, TimeSlot>
     */
    private function filterByTimeOfDay(Collection $slots, array $preferences): Collection
    {
        return $slots->filter(fn (TimeSlot $slot) => $this->matchesTimeOfDay($slot->start, $preferences));
    }

    private function matchesTimeOfDay(Carbon $time, array $preferences): bool
    {
        if ($preferences['earliest_date'] !== null && $time->lt($preferences['earliest_date'])) {
            return false;
        }

        if ($preferences['latest_date'] !== null && $time->copy()->startOfDay()->gt($preferences['latest_date'])) {
            return false;
        }

        $earliestHour = $preferences['earliest_hour'];

        if ($earliestHour !== null && $time->hour < $earliestHour) {
            return false;
        }

        return match ($preferences['time_of_day']) {
            'morning' => $time->hour < 12,
            'afternoon' => $time->hour >= ($earliestHour ?? 12),
            default => true,
        };
    }

    /**
     * @param  array{preferred_day: ?int, time_of_day: string, week_offset: int}  $preferences
     * @return list<Carbon>
     */
    private function relaxedDates(Carbon $preferredDate, array $preferences): array
    {
        $dates = [];
        $cursor = $preferredDate->copy();

        for ($i = 0; $i < 5; $i++) {
            $dates[] = $cursor->copy();
            $cursor->addWeekday();
        }

        if (($preferences['preferred_days'] ?? []) !== [] || ($preferences['preferred_day'] ?? null) !== null) {
            $dates[] = $preferredDate->copy()->addWeeks(2);
        }

        return $dates;
    }

    /**
     * @param  array{preferred_day: ?int, time_of_day: string, week_offset: int}  $preferences
     */
    private function resolvePreferredDate(array $preferences): Carbon
    {
        $earliestBookable = $this->availabilityService->earliestBookableDate();

        if ($preferences['earliest_date'] !== null && $preferences['latest_date'] !== null) {
            $target = $preferences['latest_date']->copy()->startOfDay();

            while ($target->gte($preferences['earliest_date']) && ! $this->matchesPreferredWeekday($target, $preferences)) {
                $target->subDay();
            }

            if ($target->lt($preferences['earliest_date'])) {
                $target = $preferences['earliest_date']->copy()->startOfDay();

                while ($target->lte($preferences['latest_date']) && ! $this->matchesPreferredWeekday($target, $preferences)) {
                    $target->addDay();
                }
            }

            return $target->max($earliestBookable)->startOfDay();
        }

        if ($preferences['earliest_date'] !== null) {
            $target = $preferences['earliest_date']->copy()->startOfDay()->max($earliestBookable);

            while (! $this->matchesPreferredWeekday($target, $preferences) || ! $target->isWeekday()) {
                $target->addDay();

                if ($target->diffInDays($preferences['earliest_date']) > 14) {
                    break;
                }
            }

            return $target->startOfDay();
        }

        $target = now()->addWeeks($preferences['week_offset'])->startOfDay()->max($earliestBookable);

        while (! $this->matchesPreferredWeekday($target, $preferences) || ! $target->isWeekday()) {
            $target->addDay();

            if ($target->diffInDays(now()) > 21) {
                break;
            }
        }

        return $target->startOfDay();
    }

    /**
     * @param  array{preferred_day: ?int, time_of_day: string, week_offset: int, earliest_date: ?Carbon, earliest_hour: ?int}  $preferences
     * @return list<string>
     */
    private function fallbackSlots(array $preferences): array
    {
        $base = $this->resolvePreferredDate($preferences)->setTime(
            $preferences['earliest_hour']
                ?? ($preferences['time_of_day'] === 'afternoon' ? 14 : 9),
            0,
        );

        $earliestBookable = $this->availabilityService->earliestBookableDate()->setTime($base->hour, 0);
        if ($base->lt($earliestBookable)) {
            $base = $earliestBookable->copy();
        }

        if ($preferences['earliest_date'] !== null && $base->lt($preferences['earliest_date'])) {
            $base = $preferences['earliest_date']->copy()->setTime($base->hour, 0);
        }

        if ($preferences['latest_date'] !== null && $base->copy()->startOfDay()->gt($preferences['latest_date'])) {
            $base = $preferences['latest_date']->copy()->setTime($base->hour, 0);
        }

        return [
            $base->toIso8601String(),
            $base->copy()->addHours(2)->toIso8601String(),
            $base->copy()->addWeek()->toIso8601String(),
        ];
    }

    private function isVagueSameDayRequest(string $feedback): bool
    {
        $lower = mb_strtolower($feedback);

        return str_contains($lower, 'irgendwann')
            || str_contains($lower, 'egal wann')
            || str_contains($lower, 'flexibel');
    }

    /**
     * @param  array{preferred_day: ?int, time_of_day: string, week_offset: int}  $preferences
     */
    private function isNarrowPreference(array $preferences): bool
    {
        $hasPreferredDays = ($preferences['preferred_days'] ?? []) !== []
            || ($preferences['preferred_day'] ?? null) !== null;

        return ($hasPreferredDays && $preferences['time_of_day'] !== 'any')
            || $preferences['earliest_date'] !== null
            || $preferences['latest_date'] !== null
            || $preferences['earliest_hour'] !== null;
    }
}
