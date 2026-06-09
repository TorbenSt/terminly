<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\ServiceType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerRecurringServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_assign_service_to_customer(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $company = Company::factory()->create();
        $staff = User::factory()->create(['company_id' => $company->id]);
        $staff->assignRole('staff');

        $customer = Customer::factory()->create(['company_id' => $company->id]);
        $serviceType = ServiceType::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($staff)->post(route('customers.recurring-services.store', $customer), [
            'service_type_id' => $serviceType->id,
            'next_due_at' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('recurring_services', [
            'customer_id' => $customer->id,
            'service_type_id' => $serviceType->id,
        ]);
    }
}
