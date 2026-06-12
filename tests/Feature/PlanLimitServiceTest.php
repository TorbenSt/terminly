<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\StaffMember;
use App\Services\Billing\PlanLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanLimitServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PlanLimitService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PlanLimitService::class);
    }

    public function test_limits_come_from_assigned_plan(): void
    {
        $plan = Plan::factory()->create(['included_staff' => 3, 'included_customers' => 50]);
        $company = Company::factory()->create(['plan_id' => $plan->id]);

        $this->assertSame(3, $this->service->staffLimit($company));
        $this->assertSame(50, $this->service->customerLimit($company));
    }

    public function test_default_plan_is_used_when_no_plan_assigned(): void
    {
        Plan::factory()->default()->create(['included_staff' => 5, 'included_customers' => 100]);
        $company = Company::factory()->create();

        $this->assertSame(5, $this->service->staffLimit($company));
        $this->assertSame(100, $this->service->customerLimit($company));
    }

    public function test_override_takes_precedence_over_plan(): void
    {
        $plan = Plan::factory()->create(['included_staff' => 3, 'included_customers' => 50]);
        $company = Company::factory()->create([
            'plan_id' => $plan->id,
            'staff_limit_override' => 10,
            'customer_limit_override' => 200,
        ]);

        $this->assertSame(10, $this->service->staffLimit($company));
        $this->assertSame(200, $this->service->customerLimit($company));
    }

    public function test_negative_override_means_unlimited(): void
    {
        $plan = Plan::factory()->create(['included_staff' => 3, 'included_customers' => 50]);
        $company = Company::factory()->create([
            'plan_id' => $plan->id,
            'staff_limit_override' => -1,
            'customer_limit_override' => -1,
        ]);

        $this->assertNull($this->service->staffLimit($company));
        $this->assertNull($this->service->customerLimit($company));
    }

    public function test_null_plan_limit_means_unlimited(): void
    {
        $plan = Plan::factory()->unlimited()->create();
        $company = Company::factory()->create(['plan_id' => $plan->id]);

        $this->assertNull($this->service->staffLimit($company));
        $this->assertNull($this->service->customerLimit($company));
        $this->assertSame(0, $this->service->staffOverage($company));
        $this->assertSame(0, $this->service->customerOverage($company));
    }

    public function test_overage_counts_only_active_records(): void
    {
        $plan = Plan::factory()->create(['included_staff' => 2, 'included_customers' => 1]);
        $company = Company::factory()->create(['plan_id' => $plan->id]);

        StaffMember::factory()->count(4)->create(['company_id' => $company->id, 'is_active' => true]);
        StaffMember::factory()->create(['company_id' => $company->id, 'is_active' => false]);
        Customer::factory()->count(3)->create(['company_id' => $company->id, 'is_active' => true]);
        Customer::factory()->create(['company_id' => $company->id, 'is_active' => false]);

        $this->assertSame(4, $this->service->staffUsage($company));
        $this->assertSame(2, $this->service->staffOverage($company));
        $this->assertSame(3, $this->service->customerUsage($company));
        $this->assertSame(2, $this->service->customerOverage($company));

        $summary = $this->service->summary($company);

        $this->assertSame(
            2 * $plan->extra_staff_price_cents + 2 * $plan->extra_customer_price_cents,
            $summary['extra_total_cents']
        );
    }
}
