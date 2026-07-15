<?php

namespace Tests\Unit;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ServiceType;
use App\Models\StaffAvailability;
use App\Models\StaffMember;
use App\Services\RegionalRoutingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegionalRoutingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_slots_on_day_with_existing_regional_tour(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00', 'Europe/Berlin'));

        $company = Company::factory()->create();
        $serviceType = ServiceType::factory()->create(['company_id' => $company->id, 'duration_minutes' => 60]);
        $staff = StaffMember::factory()->create(['company_id' => $company->id, 'buffer_minutes' => 0]);
        $staff->serviceTypes()->attach($serviceType->id);

        foreach (range(1, 5) as $dayOfWeek) {
            StaffAvailability::factory()->create([
                'staff_member_id' => $staff->id,
                'day_of_week' => $dayOfWeek,
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
            ]);
        }

        $tuesday = Carbon::parse('2026-07-21');
        $wednesday = Carbon::parse('2026-07-22');

        foreach (['10115', '10117'] as $plz) {
            $customer = Customer::factory()->create(['company_id' => $company->id, 'postal_code' => $plz]);
            Appointment::create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'service_type_id' => $serviceType->id,
                'staff_member_id' => $staff->id,
                'status' => AppointmentStatus::Confirmed,
                'scheduled_at' => $tuesday->copy()->setTime(9, 0),
                'duration_minutes' => 60,
                'travel_time_minutes' => 0,
            ]);
        }

        $hamburgCustomer = Customer::factory()->create(['company_id' => $company->id, 'postal_code' => '20095']);
        Appointment::create([
            'company_id' => $company->id,
            'customer_id' => $hamburgCustomer->id,
            'service_type_id' => $serviceType->id,
            'staff_member_id' => $staff->id,
            'status' => AppointmentStatus::Confirmed,
            'scheduled_at' => $wednesday->copy()->setTime(9, 0),
            'duration_minutes' => 60,
            'travel_time_minutes' => 0,
        ]);

        $slots = app(RegionalRoutingService::class)->buildRegionalSlots($staff, '10178', 60);

        $this->assertCount(3, $slots);

        $onTourDay = collect($slots)
            ->filter(fn (string $iso) => Carbon::parse($iso)->toDateString() === $tuesday->toDateString())
            ->count();

        $this->assertGreaterThanOrEqual(2, $onTourDay);

        foreach ($slots as $iso) {
            $this->assertNotSame(
                Carbon::parse('2026-07-22')->toDateString(),
                Carbon::parse($iso)->toDateString(),
                'Hamburg tour day should be avoided for Berlin customer',
            );
        }

        Carbon::setTestNow();
    }
}
