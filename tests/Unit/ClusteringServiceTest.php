<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Customer;
use App\Models\RecurringService;
use App\Models\ServiceType;
use App\Services\ClusteringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClusteringServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_groups_services_by_postal_region(): void
    {
        $company = Company::factory()->create();
        $serviceType = ServiceType::factory()->create(['company_id' => $company->id]);

        $customerBerlin = Customer::factory()->create([
            'company_id' => $company->id,
            'postal_code' => '10115',
        ]);
        $customerBerlin2 = Customer::factory()->create([
            'company_id' => $company->id,
            'postal_code' => '10117',
        ]);
        $customerHamburg = Customer::factory()->create([
            'company_id' => $company->id,
            'postal_code' => '20095',
        ]);

        foreach ([$customerBerlin, $customerBerlin2, $customerHamburg] as $customer) {
            RecurringService::factory()->create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'service_type_id' => $serviceType->id,
                'next_due_at' => now()->subDay(),
            ]);
        }

        $clusters = app(ClusteringService::class)->clusterDueServices($company);

        $this->assertCount(2, $clusters);
        $this->assertEquals(2, $clusters->first()->jobs->count());
    }
}
