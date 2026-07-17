<?php

namespace Tests\Unit;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ServiceType;
use App\Models\StaffMember;
use App\Services\StaffLoadBalancerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffLoadBalancerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_picks_less_loaded_qualified_staff(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00', 'UTC'));

        $company = Company::factory()->create();
        $serviceType = ServiceType::factory()->create(['company_id' => $company->id]);
        $busy = StaffMember::factory()->create(['company_id' => $company->id, 'name' => 'Busy']);
        $free = StaffMember::factory()->create(['company_id' => $company->id, 'name' => 'Free']);
        $busy->serviceTypes()->attach($serviceType->id);
        $free->serviceTypes()->attach($serviceType->id);

        for ($i = 0; $i < 5; $i++) {
            $customer = Customer::factory()->create(['company_id' => $company->id, 'postal_code' => '10115']);
            Appointment::create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'service_type_id' => $serviceType->id,
                'staff_member_id' => $busy->id,
                'status' => AppointmentStatus::Confirmed,
                'scheduled_at' => now()->addDays(2 + $i)->setTime(9, 0),
                'duration_minutes' => 60,
                'travel_time_minutes' => 0,
            ]);
        }

        $pool = collect([
            [
                'staff_id' => $busy->id,
                'qualified_service_type_ids' => [$serviceType->id],
                'upcoming_workload' => 5,
            ],
            [
                'staff_id' => $free->id,
                'qualified_service_type_ids' => [$serviceType->id],
                'upcoming_workload' => 0,
            ],
        ]);

        $batch = [];
        $picked = app(StaffLoadBalancerService::class)->pickQualifiedStaff(
            $pool,
            $serviceType->id,
            $company->id,
            '10178',
            $batch,
        );

        $this->assertSame($free->id, $picked['staff_id']);

        Carbon::setTestNow();
    }

    public function test_spreads_assignments_across_qualified_staff_in_batch(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00', 'UTC'));

        $company = Company::factory()->create();
        $serviceType = ServiceType::factory()->create(['company_id' => $company->id]);
        $anna = StaffMember::factory()->create(['company_id' => $company->id, 'name' => 'Anna']);
        $ben = StaffMember::factory()->create(['company_id' => $company->id, 'name' => 'Ben']);
        $anna->serviceTypes()->attach($serviceType->id);
        $ben->serviceTypes()->attach($serviceType->id);

        $pool = collect([
            [
                'staff_id' => $anna->id,
                'qualified_service_type_ids' => [$serviceType->id],
                'upcoming_workload' => 0,
            ],
            [
                'staff_id' => $ben->id,
                'qualified_service_type_ids' => [$serviceType->id],
                'upcoming_workload' => 0,
            ],
        ]);

        $balancer = app(StaffLoadBalancerService::class);
        $batch = [];
        $first = $balancer->pickQualifiedStaff($pool, $serviceType->id, $company->id, null, $batch);
        $second = $balancer->pickQualifiedStaff($pool, $serviceType->id, $company->id, null, $batch);

        $this->assertNotSame($first['staff_id'], $second['staff_id']);
        $this->assertEqualsCanonicalizing(
            [$anna->id, $ben->id],
            [$first['staff_id'], $second['staff_id']],
        );

        Carbon::setTestNow();
    }

    public function test_rebalance_assignments_uses_less_loaded_staff(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00', 'UTC'));

        $company = Company::factory()->create();
        $serviceType = ServiceType::factory()->create(['company_id' => $company->id]);
        $busy = StaffMember::factory()->create(['company_id' => $company->id]);
        $free = StaffMember::factory()->create(['company_id' => $company->id]);
        $busy->serviceTypes()->attach($serviceType->id);
        $free->serviceTypes()->attach($serviceType->id);

        $customer = Customer::factory()->create(['company_id' => $company->id, 'postal_code' => '80331']);
        Appointment::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'service_type_id' => $serviceType->id,
            'staff_member_id' => $busy->id,
            'status' => AppointmentStatus::Confirmed,
            'scheduled_at' => now()->addDays(3)->setTime(9, 0),
            'duration_minutes' => 60,
            'travel_time_minutes' => 0,
        ]);

        $pool = collect([
            [
                'staff_id' => $busy->id,
                'qualified_service_type_ids' => [$serviceType->id],
                'upcoming_workload' => 1,
            ],
            [
                'staff_id' => $free->id,
                'qualified_service_type_ids' => [$serviceType->id],
                'upcoming_workload' => 0,
            ],
        ]);

        $assignments = collect([
            [
                'customer_id' => $customer->id,
                'staff_id' => $busy->id,
                'service_type_id' => $serviceType->id,
                'slots' => [now()->addWeek()->toIso8601String()],
            ],
        ]);

        $result = app(StaffLoadBalancerService::class)->rebalanceAssignments(
            $assignments,
            $pool,
            $company->id,
            [$customer->id => '80331'],
        );

        $this->assertSame($free->id, $result->first()['staff_id']);

        Carbon::setTestNow();
    }
}
