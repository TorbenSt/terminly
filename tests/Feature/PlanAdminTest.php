<?php

namespace Tests\Feature;

use App\Jobs\SyncCompanyUsageJob;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PlanAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function createSuperAdmin(): User
    {
        $user = User::factory()->create(['company_id' => null]);
        $user->assignRole('super_admin');

        return $user;
    }

    protected function planPayload(array $overrides = []): array
    {
        return [
            'name' => 'Super Pro',
            'description' => 'Großes Abo',
            'price_cents' => 9900,
            'included_staff' => 20,
            'included_customers' => 500,
            'extra_staff_price_cents' => 400,
            'extra_customer_price_cents' => 30,
            'is_active' => true,
            'is_default' => false,
            ...$overrides,
        ];
    }

    public function test_super_admin_can_create_plan(): void
    {
        $this->actingAs($this->createSuperAdmin())
            ->post(route('admin.plans.store'), $this->planPayload())
            ->assertSessionHas('success');

        $this->assertDatabaseHas('plans', ['name' => 'Super Pro', 'price_cents' => 9900]);
    }

    public function test_company_admin_cannot_manage_plans(): void
    {
        $company = Company::factory()->billingExempt()->create();
        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->assignRole('company_admin');

        $this->actingAs($admin)
            ->post(route('admin.plans.store'), $this->planPayload())
            ->assertForbidden();
    }

    public function test_creating_default_plan_unsets_previous_default(): void
    {
        $first = Plan::factory()->default()->create();

        $this->actingAs($this->createSuperAdmin())
            ->post(route('admin.plans.store'), $this->planPayload(['is_default' => true]));

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue(Plan::where('name', 'Super Pro')->firstOrFail()->is_default);
    }

    public function test_plan_used_by_companies_is_deactivated_instead_of_deleted(): void
    {
        $plan = Plan::factory()->create();
        Company::factory()->create(['plan_id' => $plan->id]);

        $this->actingAs($this->createSuperAdmin())
            ->delete(route('admin.plans.destroy', $plan));

        $this->assertDatabaseHas('plans', ['id' => $plan->id, 'is_active' => false]);
    }

    public function test_unused_plan_can_be_deleted(): void
    {
        $plan = Plan::factory()->create();

        $this->actingAs($this->createSuperAdmin())
            ->delete(route('admin.plans.destroy', $plan));

        $this->assertDatabaseMissing('plans', ['id' => $plan->id]);
    }

    public function test_super_admin_can_update_company_billing_settings(): void
    {
        $plan = Plan::factory()->create();
        $company = Company::factory()->create();

        $this->actingAs($this->createSuperAdmin())
            ->patch(route('admin.companies.update', $company), [
                'plan_id' => $plan->id,
                'billing_exempt' => true,
                'is_active' => true,
                'staff_limit_override' => -1,
                'customer_limit_override' => 25,
                'trial_ends_at' => now()->addDays(60)->toDateString(),
            ])
            ->assertSessionHas('success');

        $company->refresh();

        $this->assertSame($plan->id, $company->plan_id);
        $this->assertTrue($company->billing_exempt);
        $this->assertSame(-1, $company->staff_limit_override);
        $this->assertSame(25, $company->customer_limit_override);
        $this->assertSame(now()->addDays(60)->toDateString(), $company->trial_ends_at->toDateString());
    }

    public function test_creating_customer_dispatches_usage_sync_job(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        Customer::factory()->create(['company_id' => $company->id]);

        Queue::assertPushed(SyncCompanyUsageJob::class, fn ($job) => $job->companyId === $company->id);
    }
}
