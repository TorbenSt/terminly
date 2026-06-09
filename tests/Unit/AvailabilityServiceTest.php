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
}
