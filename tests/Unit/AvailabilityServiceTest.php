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
}
