<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use App\Services\Prospect\ProspectDeduplicationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProspectSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function companyAdmin(Company $company): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole('company_admin');

        return $user;
    }

    public function test_prospect_hub_accessible_without_feature_for_upsell(): void
    {
        $company = Company::factory()->expiredTrial()->create();
        $admin = $this->companyAdmin($company);

        $this->actingAs($admin)
            ->get(route('prospects.index'))
            ->assertOk();
    }

    public function test_prospect_search_run_blocked_without_access(): void
    {
        $company = Company::factory()->billingExempt()->create();
        $company->update(['billing_exempt' => false, 'trial_ends_at' => now()->subDay()]);
        $admin = $this->companyAdmin($company);

        $profile = \App\Models\ProspectSearchProfile::create([
            'company_id' => $company->id,
            'name' => 'Test',
            'industries' => ['Heizung'],
            'postal_code' => '10115',
            'radius_km' => 10,
            'max_results_per_run' => 10,
        ]);

        $this->actingAs($admin)
            ->post(route('prospects.profiles.run', $profile))
            ->assertRedirect(route('billing.index'));
    }

    public function test_billing_exempt_company_can_start_search(): void
    {
        $company = Company::factory()->billingExempt()->create();
        $admin = $this->companyAdmin($company);

        $profile = \App\Models\ProspectSearchProfile::create([
            'company_id' => $company->id,
            'name' => 'Test',
            'industries' => ['Heizung'],
            'postal_code' => '10115',
            'radius_km' => 10,
            'max_results_per_run' => 10,
        ]);

        $this->actingAs($admin)
            ->post(route('prospects.profiles.run', $profile))
            ->assertSessionHas('success');
    }

    public function test_deduplication_detects_existing_customer_by_name(): void
    {
        $company = Company::factory()->create();
        Customer::factory()->create([
            'company_id' => $company->id,
            'name' => 'Müller Heizung GmbH',
        ]);

        $service = app(ProspectDeduplicationService::class);

        $this->assertTrue($service->isDuplicate($company, [
            'google_place_id' => 'places/test1',
            'company_name' => 'Müller Heizung GmbH',
            'postal_code' => '10115',
            'city' => 'Berlin',
        ], true));
    }

    public function test_plan_with_includes_prospect_search_grants_access(): void
    {
        $plan = \App\Models\Plan::factory()->create(['includes_prospect_search' => true]);
        $company = Company::factory()->create(['plan_id' => $plan->id, 'billing_exempt' => false, 'trial_ends_at' => null]);

        $this->assertTrue($company->hasProspectSearchAccess());
    }
}
