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

    public function test_parse_feedback_recognizes_multiple_weekdays(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00', 'UTC'));

        $preferences = app(SlotCuratorService::class)->parseFeedback(
            'bitte erst ab dem 14.08. entweder Dienstag oder Donnerstag nachmittag',
        );

        $this->assertSame([Carbon::TUESDAY, Carbon::THURSDAY], $preferences['preferred_days']);
        $this->assertSame('afternoon', $preferences['time_of_day']);
        $this->assertSame('2026-08-14', $preferences['earliest_date']?->toDateString());

        Carbon::setTestNow();
    }

    public function test_negotiation_avoids_foreign_plz_tour_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00', 'UTC'));

        $company = \App\Models\Company::factory()->create();
        $staff = StaffMember::factory()->create(['company_id' => $company->id, 'buffer_minutes' => 0]);
        $serviceType = \App\Models\ServiceType::factory()->create([
            'company_id' => $company->id,
            'duration_minutes' => 60,
        ]);

        foreach (range(Carbon::MONDAY, Carbon::FRIDAY) as $dayOfWeek) {
            StaffAvailability::factory()->create([
                'staff_member_id' => $staff->id,
                'day_of_week' => $dayOfWeek,
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
            ]);
        }

        $frankfurtDay = Carbon::parse('2026-08-25', 'UTC'); // Tuesday
        foreach (['60311', '60313'] as $index => $plz) {
            $customer = \App\Models\Customer::factory()->create([
                'company_id' => $company->id,
                'postal_code' => $plz,
            ]);
            \App\Models\Appointment::create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'service_type_id' => $serviceType->id,
                'staff_member_id' => $staff->id,
                'status' => \App\Enums\AppointmentStatus::Confirmed,
                'scheduled_at' => $frankfurtDay->copy()->setTime(8 + ($index * 2), 0),
                'duration_minutes' => 60,
                'travel_time_minutes' => 0,
            ]);
        }

        $berlinDay = Carbon::parse('2026-08-27', 'UTC'); // Thursday
        foreach (['10115', '10117'] as $index => $plz) {
            $customer = \App\Models\Customer::factory()->create([
                'company_id' => $company->id,
                'postal_code' => $plz,
            ]);
            \App\Models\Appointment::create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'service_type_id' => $serviceType->id,
                'staff_member_id' => $staff->id,
                'status' => \App\Enums\AppointmentStatus::Confirmed,
                'scheduled_at' => $berlinDay->copy()->setTime(8 + ($index * 2), 0),
                'duration_minutes' => 60,
                'travel_time_minutes' => 0,
            ]);
        }

        $result = app(SlotCuratorService::class)->curate(
            $staff,
            'bitte erst ab dem 14.08. entweder Dienstag oder Donnerstag nachmittag',
            60,
            '10178',
        );

        $this->assertCount(3, $result['slots']);

        foreach ($result['slots'] as $iso) {
            $slot = Carbon::parse($iso);
            $this->assertContains($slot->dayOfWeek, [Carbon::TUESDAY, Carbon::THURSDAY]);
            $this->assertGreaterThanOrEqual(12, $slot->hour);
            $this->assertNotSame(
                $frankfurtDay->toDateString(),
                $slot->toDateString(),
                'Must not propose Berlin customer onto Frankfurt tour day',
            );
        }

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
