<?php

namespace Tests\Unit;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ServiceType;
use App\Models\StaffMember;
use App\Services\ArrivalWindowService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArrivalWindowServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'scheduling.arrival_window_base_minutes' => 30,
            'scheduling.arrival_window_increment_minutes' => 15,
            'scheduling.arrival_window_max_minutes' => 90,
        ]);
    }

    public function test_window_grows_with_prior_appointments_on_same_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00', 'UTC'));

        $company = Company::factory()->create(['timezone' => 'Europe/Berlin']);
        $staff = StaffMember::factory()->create(['company_id' => $company->id]);
        $serviceType = ServiceType::factory()->create(['company_id' => $company->id, 'duration_minutes' => 60]);
        $day = Carbon::parse('2026-07-21', 'UTC');

        foreach (['08:00', '10:00', '12:00'] as $time) {
            $customer = Customer::factory()->create(['company_id' => $company->id]);
            Appointment::create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'service_type_id' => $serviceType->id,
                'staff_member_id' => $staff->id,
                'status' => AppointmentStatus::Confirmed,
                'scheduled_at' => $day->copy()->setTimeFromTimeString($time),
                'duration_minutes' => 60,
                'travel_time_minutes' => 0,
            ]);
        }

        $service = app(ArrivalWindowService::class);
        $firstWindow = $service->forSlot($day->copy()->setTime(8, 0), $staff, $company);
        $fourthWindow = $service->forSlot($day->copy()->setTime(14, 0), $staff, $company);

        $this->assertSame(0, $firstWindow->priorAppointmentsCount);
        $this->assertSame(30, $firstWindow->widthMinutes);
        $this->assertSame(3, $fourthWindow->priorAppointmentsCount);
        $this->assertSame(75, $fourthWindow->widthMinutes);
        $this->assertTrue($fourthWindow->start->copy()->addMinutes(75)->eq($fourthWindow->end));

        $label = $service->formatLabel($fourthWindow, $company);
        $this->assertStringContainsString('Ankunft 16:00–17:15 Uhr', $label);

        Carbon::setTestNow();
    }

    public function test_window_width_is_capped_at_maximum(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00', 'UTC'));

        $company = Company::factory()->create();
        $staff = StaffMember::factory()->create(['company_id' => $company->id]);
        $serviceType = ServiceType::factory()->create(['company_id' => $company->id, 'duration_minutes' => 60]);
        $day = Carbon::parse('2026-07-21', 'UTC');

        for ($hour = 6; $hour < 14; $hour++) {
            $customer = Customer::factory()->create(['company_id' => $company->id]);
            Appointment::create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'service_type_id' => $serviceType->id,
                'staff_member_id' => $staff->id,
                'status' => AppointmentStatus::Confirmed,
                'scheduled_at' => $day->copy()->setTime($hour, 0),
                'duration_minutes' => 60,
                'travel_time_minutes' => 0,
            ]);
        }

        $window = app(ArrivalWindowService::class)->forSlot($day->copy()->setTime(15, 0), $staff, $company);

        $this->assertSame(90, $window->widthMinutes);

        Carbon::setTestNow();
    }

    public function test_excludes_current_appointment_from_prior_count(): void
    {
        $company = Company::factory()->create();
        $staff = StaffMember::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->create(['company_id' => $company->id]);
        $serviceType = ServiceType::factory()->create(['company_id' => $company->id]);
        $slot = Carbon::parse('2026-07-21 09:00:00', 'UTC');

        $appointment = Appointment::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'service_type_id' => $serviceType->id,
            'staff_member_id' => $staff->id,
            'status' => AppointmentStatus::Proposed,
            'scheduled_at' => $slot,
            'duration_minutes' => 60,
            'travel_time_minutes' => 0,
        ]);

        $window = app(ArrivalWindowService::class)->forSlot($slot, $staff, $company, $appointment->id);

        $this->assertSame(0, $window->priorAppointmentsCount);
        $this->assertSame(30, $window->widthMinutes);
    }
}
