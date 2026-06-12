<?php

namespace Tests\Feature;

use App\Models\BillingSetting;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function createCompanyAdmin(Company $company): User
    {
        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->assignRole('company_admin');

        return $admin;
    }

    protected function customerPayload(): array
    {
        return [
            'name' => 'Test Kunde',
            'address' => 'Teststraße 1',
            'postal_code' => '10115',
            'city' => 'Berlin',
        ];
    }

    public function test_company_without_subscription_or_trial_is_read_only(): void
    {
        $company = Company::factory()->expiredTrial()->create();
        $admin = $this->createCompanyAdmin($company);

        $this->actingAs($admin)
            ->get(route('customers.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('customers.store'), $this->customerPayload())
            ->assertRedirect(route('billing.index'));

        $this->assertSame(0, Customer::count());
    }

    public function test_staff_write_is_blocked_without_subscription(): void
    {
        $company = Company::factory()->expiredTrial()->create();
        $staff = User::factory()->create(['company_id' => $company->id]);
        $staff->assignRole('staff');

        $response = $this->actingAs($staff)
            ->from(route('customers.index'))
            ->post(route('customers.store'), $this->customerPayload());

        $response->assertRedirect(route('customers.index'));
        $response->assertSessionHas('error');
        $this->assertSame(0, Customer::count());
    }

    public function test_company_on_trial_can_write(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createCompanyAdmin($company);

        $this->actingAs($admin)
            ->post(route('customers.store'), $this->customerPayload())
            ->assertSessionHas('success');

        $this->assertSame(1, Customer::count());
    }

    public function test_billing_exempt_company_can_write(): void
    {
        $company = Company::factory()->billingExempt()->create();
        $admin = $this->createCompanyAdmin($company);

        $this->actingAs($admin)
            ->post(route('customers.store'), $this->customerPayload())
            ->assertSessionHas('success');

        $this->assertSame(1, Customer::count());
    }

    public function test_billing_page_is_reachable_without_active_subscription(): void
    {
        $company = Company::factory()->expiredTrial()->create();
        $admin = $this->createCompanyAdmin($company);

        $this->actingAs($admin)
            ->get(route('billing.index'))
            ->assertOk();
    }

    public function test_staff_cannot_access_billing_page(): void
    {
        $company = Company::factory()->create();
        $staff = User::factory()->create(['company_id' => $company->id]);
        $staff->assignRole('staff');

        $this->actingAs($staff)
            ->get(route('billing.index'))
            ->assertForbidden();
    }

    public function test_new_company_gets_default_trial(): void
    {
        $superAdmin = User::factory()->create(['company_id' => null]);
        $superAdmin->assignRole('super_admin');

        $this->actingAs($superAdmin)->post(route('admin.companies.store'), [
            'name' => 'Neue Firma',
            'timezone' => 'Europe/Berlin',
        ]);

        $company = Company::where('name', 'Neue Firma')->firstOrFail();

        $this->assertNotNull($company->trial_ends_at);
        $this->assertTrue($company->trial_ends_at->isFuture());
        $this->assertEqualsWithDelta(
            now()->addDays(BillingSetting::defaultTrialDays())->timestamp,
            $company->trial_ends_at->timestamp,
            5
        );
    }

    public function test_trial_days_setting_of_zero_disables_trial(): void
    {
        BillingSetting::set('default_trial_days', '0');

        $superAdmin = User::factory()->create(['company_id' => null]);
        $superAdmin->assignRole('super_admin');

        $this->actingAs($superAdmin)->post(route('admin.companies.store'), [
            'name' => 'Firma ohne Trial',
            'timezone' => 'Europe/Berlin',
        ]);

        $company = Company::where('name', 'Firma ohne Trial')->firstOrFail();

        $this->assertNull($company->trial_ends_at);
    }
}
