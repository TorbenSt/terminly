<?php

namespace Tests\Unit;

use App\AI\GrokSchedulerService;
use App\Models\Company;
use App\Models\Customer;
use App\Models\RecurringService;
use App\Models\ServiceType;
use App\Models\StaffAvailability;
use App\Models\StaffMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrokSchedulerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_fallback_generates_assignments_without_api_key(): void
    {
        config(['grok.api_key' => null]);

        $company = Company::factory()->create();
        $serviceType = ServiceType::factory()->create(['company_id' => $company->id, 'duration_minutes' => 60]);
        $staff = StaffMember::factory()->create(['company_id' => $company->id]);
        $staff->serviceTypes()->attach($serviceType->id);

        StaffAvailability::factory()->create([
            'staff_member_id' => $staff->id,
            'day_of_week' => now()->addWeekday()->dayOfWeek,
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        $customer = Customer::factory()->create(['company_id' => $company->id, 'postal_code' => '10115']);
        RecurringService::factory()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'service_type_id' => $serviceType->id,
            'next_due_at' => now()->subDay(),
        ]);

        $assignments = app(GrokSchedulerService::class)->generateProposalsForCompany($company);

        $this->assertNotEmpty($assignments);
        $this->assertArrayHasKey('slots', $assignments->first());
        $this->assertCount(3, $assignments->first()['slots']);
    }
}
