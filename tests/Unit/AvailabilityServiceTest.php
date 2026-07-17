<?php

namespace Tests\Unit;

use App\Models\StaffAvailability;
use App\Models\StaffMember;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_slots_within_working_hours(): void
    {
        $staff = StaffMember::factory()->create(['buffer_minutes' => 15]);

        StaffAvailability::factory()->create([
            'staff_member_id' => $staff->id,
            'day_of_week' => Carbon::parse('next monday')->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
        ]);

        $date = Carbon::parse('next monday')->setTime(0, 0);
        $service = app(AvailabilityService::class);
        $slots = $service->getAvailableSlots($staff, $date, 60);

        $this->assertNotEmpty($slots);
        $this->assertTrue($slots->first()->start->gte($date->copy()->setTime(9, 0)));
    }

    public function test_excludes_slots_during_daily_break(): void
    {
        $staff = StaffMember::factory()->create(['buffer_minutes' => 0]);
        $date = Carbon::parse('next monday')->setTime(0, 0);

        StaffAvailability::factory()->create([
            'staff_member_id' => $staff->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break_start_time' => '12:00:00',
            'break_end_time' => '13:00:00',
        ]);

        $service = app(AvailabilityService::class);
        $slots = $service->getAvailableSlots($staff, $date, 60);

        $this->assertTrue(
            $slots->every(fn ($slot) => $slot->start->format('H:i') !== '12:00')
        );
        $this->assertTrue(
            $slots->contains(fn ($slot) => $slot->start->format('H:i') === '11:00')
        );
        $this->assertTrue(
            $slots->contains(fn ($slot) => $slot->start->format('H:i') === '13:00')
        );
    }

    public function test_earliest_bookable_date_skips_same_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00', 'UTC')); // Tuesday

        $service = app(AvailabilityService::class);

        $this->assertSame('2026-07-15', $service->earliestBookableDate()->toDateString());
        $this->assertFalse($service->isBookableDate(Carbon::parse('2026-07-14', 'UTC')));
        $this->assertTrue($service->isBookableDate(Carbon::parse('2026-07-15', 'UTC')));

        Carbon::setTestNow(Carbon::parse('2026-07-17 10:00:00', 'UTC')); // Friday
        $this->assertSame('2026-07-20', $service->earliestBookableDate()->toDateString()); // Monday

        Carbon::setTestNow();
    }

    public function test_bookable_slots_exclude_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00', 'UTC'));

        $staff = StaffMember::factory()->create(['buffer_minutes' => 0]);
        StaffAvailability::factory()->create([
            'staff_member_id' => $staff->id,
            'day_of_week' => Carbon::TUESDAY,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
        ]);

        $service = app(AvailabilityService::class);
        $today = Carbon::parse('2026-07-14', 'UTC');

        $this->assertNotEmpty($service->getAvailableSlots($staff, $today, 60));
        $this->assertEmpty($service->getBookableSlots($staff, $today, 60));

        Carbon::setTestNow();
    }
}
