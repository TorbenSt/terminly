<?php

namespace Tests\Unit;

use App\Models\StaffAvailability;
use App\Models\StaffMember;
use App\Services\SlotCuratorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class SlotCuratorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_curates_diverse_slots_for_monday_morning_preference(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00', 'Europe/Berlin'));

        $staff = StaffMember::factory()->create(['buffer_minutes' => 0]);

        foreach ([Carbon::MONDAY, Carbon::TUESDAY] as $dayOfWeek) {
            StaffAvailability::factory()->create([
                'staff_member_id' => $staff->id,
                'day_of_week' => $dayOfWeek,
                'start_time' => '08:00:00',
                'end_time' => '12:00:00',
            ]);
        }

        $result = app(SlotCuratorService::class)->curate(
            $staff,
            'Gerne Montag vormittags in einer Woche, am besten gegen 9 Uhr.',
            60,
        );

        $this->assertCount(3, $result['slots']);
        $this->assertSame(0, $result['recommended_index']);

        $slots = collect($result['slots'])->map(fn (string $iso) => Carbon::parse($iso))->values();
        $sorted = $slots->sortBy(fn (Carbon $slot) => $slot->timestamp)->values();

        $this->assertTrue($slots->every(fn (Carbon $slot, int $index) => $slot->eq($sorted[$index])));
        $this->assertSame(0, $result['recommended_index']);

        foreach ($slots as $i => $left) {
            for ($j = $i + 1; $j < $slots->count(); $j++) {
                $right = $slots[$j];

                if ($left->isSameDay($right)) {
                    $this->assertGreaterThanOrEqual(
                        120,
                        $left->diffInMinutes($right),
                        'Same-day slots must be at least 2 hours apart',
                    );
                }
            }
        }

        $uniqueDays = $slots->map(fn (Carbon $s) => $s->toDateString())->unique();

        $this->assertGreaterThanOrEqual(2, $uniqueDays->count());
        $this->assertFalse($this->areConsecutiveQuarterHourSlots($slots));

        Carbon::setTestNow();
    }

    public function test_honors_earliest_date_and_afternoon_hour_constraint(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00', 'Europe/Berlin'));

        $staff = StaffMember::factory()->create(['buffer_minutes' => 0]);

        foreach (range(Carbon::MONDAY, Carbon::FRIDAY) as $dayOfWeek) {
            StaffAvailability::factory()->create([
                'staff_member_id' => $staff->id,
                'day_of_week' => $dayOfWeek,
                'start_time' => '08:00:00',
                'end_time' => '18:00:00',
            ]);
        }

        $result = app(SlotCuratorService::class)->curate(
            $staff,
            'Bitte erst ab dem ersten August nachmittags ab 15 Uhr',
            60,
        );

        $this->assertCount(3, $result['slots']);

        $earliestAllowed = Carbon::parse('2026-08-01 15:00:00', 'Europe/Berlin');

        foreach ($result['slots'] as $iso) {
            $slot = Carbon::parse($iso);
            $this->assertTrue(
                $slot->gte($earliestAllowed),
                "Slot {$slot->toDateTimeString()} violates earliest date/time constraint",
            );
        }

        Carbon::setTestNow();
    }

    public function test_parse_feedback_recognizes_end_of_month_preference(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00', 'Europe/Berlin'));

        $preferences = app(SlotCuratorService::class)->parseFeedback('Lieber Ende August');

        $this->assertSame('2026-08-22', $preferences['earliest_date']->toDateString());
        $this->assertSame('2026-08-31', $preferences['latest_date']->toDateString());

        Carbon::setTestNow();
    }

    public function test_honors_end_of_month_preference_when_curating_slots(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00', 'Europe/Berlin'));

        $staff = StaffMember::factory()->create(['buffer_minutes' => 0]);

        foreach (range(Carbon::MONDAY, Carbon::FRIDAY) as $dayOfWeek) {
            StaffAvailability::factory()->create([
                'staff_member_id' => $staff->id,
                'day_of_week' => $dayOfWeek,
                'start_time' => '08:00:00',
                'end_time' => '18:00:00',
            ]);
        }

        $result = app(SlotCuratorService::class)->curate(
            $staff,
            'Lieber Ende August',
            60,
        );

        $this->assertCount(3, $result['slots']);

        $windowStart = Carbon::parse('2026-08-22', 'Europe/Berlin')->startOfDay();
        $windowEnd = Carbon::parse('2026-08-31', 'Europe/Berlin')->endOfDay();

        foreach ($result['slots'] as $iso) {
            $slot = Carbon::parse($iso);
            $this->assertTrue(
                $slot->between($windowStart, $windowEnd),
                "Slot {$slot->toDateTimeString()} is outside end-of-August window",
            );
        }

        Carbon::setTestNow();
    }

    /**
     * @param  Collection<int, Carbon>  $slots
     */
    private function areConsecutiveQuarterHourSlots(Collection $slots): bool
    {
        $sorted = $slots->sortBy(fn (Carbon $s) => $s->timestamp)->values();

        return abs($sorted[0]->diffInMinutes($sorted[1])) === 15
            && abs($sorted[1]->diffInMinutes($sorted[2])) === 15
            && $sorted[0]->isSameDay($sorted[2]);
    }
}
