<?php

namespace Tests\Unit;

use App\DTOs\StaffBindingPreference;
use App\Enums\AppointmentStatus;
use App\Enums\DeadlinePhase;
use App\Enums\StaffCustomerBinding;
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

    public function test_prefer_mode_picks_primary_even_when_busier(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00', 'UTC'));

        $company = Company::factory()->create(['staff_customer_binding' => StaffCustomerBinding::Prefer]);
        $serviceType = ServiceType::factory()->create(['company_id' => $company->id]);
        $primary = StaffMember::factory()->create(['company_id' => $company->id, 'name' => 'Primary']);
        $other = StaffMember::factory()->create(['company_id' => $company->id, 'name' => 'Other']);
        $primary->serviceTypes()->attach($serviceType->id);
        $other->serviceTypes()->attach($serviceType->id);

        $pool = collect([
            [
                'staff_id' => $primary->id,
                'qualified_service_type_ids' => [$serviceType->id],
                'upcoming_workload' => 8,
            ],
            [
                'staff_id' => $other->id,
                'qualified_service_type_ids' => [$serviceType->id],
                'upcoming_workload' => 0,
            ],
        ]);

        $batch = [];
        $picked = app(StaffLoadBalancerService::class)->pickQualifiedStaff(
            $pool,
            $serviceType->id,
            $company->id,
            null,
            $batch,
            new StaffBindingPreference(
                mode: StaffCustomerBinding::Prefer,
                primaryStaffId: $primary->id,
                phase: DeadlinePhase::Green,
            ),
        );

        $this->assertSame($primary->id, $picked['staff_id']);

        Carbon::setTestNow();
    }

    public function test_hard_mode_returns_null_without_preferred_staff(): void
    {
        $company = Company::factory()->create(['staff_customer_binding' => StaffCustomerBinding::Hard]);
        $serviceType = ServiceType::factory()->create(['company_id' => $company->id]);
        $other = StaffMember::factory()->create(['company_id' => $company->id]);
        $other->serviceTypes()->attach($serviceType->id);

        $pool = collect([
            [
                'staff_id' => $other->id,
                'qualified_service_type_ids' => [$serviceType->id],
                'upcoming_workload' => 0,
            ],
        ]);

        $batch = [];
        $picked = app(StaffLoadBalancerService::class)->pickQualifiedStaff(
            $pool,
            $serviceType->id,
            $company->id,
            null,
            $batch,
            new StaffBindingPreference(
                mode: StaffCustomerBinding::Hard,
                primaryStaffId: 99999,
                phase: DeadlinePhase::Green,
            ),
        );

        $this->assertNull($picked);
    }

    public function test_strict_green_uses_backup_when_primary_unqualified(): void
    {
        $serviceType = ServiceType::factory()->create();
        $company = $serviceType->company;
        $company->update(['staff_customer_binding' => StaffCustomerBinding::StrictWithExceptions]);

        $primary = StaffMember::factory()->create(['company_id' => $company->id]);
        $backup = StaffMember::factory()->create(['company_id' => $company->id]);
        $other = StaffMember::factory()->create(['company_id' => $company->id]);
        $backup->serviceTypes()->attach($serviceType->id);
        $other->serviceTypes()->attach($serviceType->id);

        $pool = collect([
            [
                'staff_id' => $backup->id,
                'qualified_service_type_ids' => [$serviceType->id],
                'upcoming_workload' => 3,
            ],
            [
                'staff_id' => $other->id,
                'qualified_service_type_ids' => [$serviceType->id],
                'upcoming_workload' => 0,
            ],
        ]);

        $batch = [];
        $picked = app(StaffLoadBalancerService::class)->pickQualifiedStaff(
            $pool,
            $serviceType->id,
            $company->id,
            null,
            $batch,
            new StaffBindingPreference(
                mode: StaffCustomerBinding::StrictWithExceptions,
                primaryStaffId: $primary->id,
                backupStaffId: $backup->id,
                phase: DeadlinePhase::Green,
            ),
        );

        $this->assertSame($backup->id, $picked['staff_id']);
    }

    public function test_strict_red_allows_other_qualified_staff(): void
    {
        $serviceType = ServiceType::factory()->create();
        $company = $serviceType->company;

        $primary = StaffMember::factory()->create(['company_id' => $company->id]);
        $other = StaffMember::factory()->create(['company_id' => $company->id]);
        $primary->serviceTypes()->attach($serviceType->id);
        $other->serviceTypes()->attach($serviceType->id);

        $pool = collect([
            [
                'staff_id' => $primary->id,
                'qualified_service_type_ids' => [$serviceType->id],
                'upcoming_workload' => 20,
            ],
            [
                'staff_id' => $other->id,
                'qualified_service_type_ids' => [$serviceType->id],
                'upcoming_workload' => 0,
            ],
        ]);

        $batch = [];
        $picked = app(StaffLoadBalancerService::class)->pickQualifiedStaff(
            $pool,
            $serviceType->id,
            $company->id,
            null,
            $batch,
            new StaffBindingPreference(
                mode: StaffCustomerBinding::StrictWithExceptions,
                primaryStaffId: $primary->id,
                phase: DeadlinePhase::Red,
            ),
        );

        // Soft boost (500) is less than one extra appointment (100), so free tech wins.
        $this->assertSame($other->id, $picked['staff_id']);
    }

    public function test_deadline_phase_thresholds(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00', 'UTC'));
        $balancer = app(StaffLoadBalancerService::class);

        $this->assertSame(
            DeadlinePhase::Green,
            $balancer->deadlinePhase(now()->subDays(2), 14),
        );
        $this->assertSame(
            DeadlinePhase::Yellow,
            $balancer->deadlinePhase(now()->subDays(8), 14),
        );
        $this->assertSame(
            DeadlinePhase::Red,
            $balancer->deadlinePhase(now()->subDays(12), 14),
        );

        Carbon::setTestNow();
    }
}
