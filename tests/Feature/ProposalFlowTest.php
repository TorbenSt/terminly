<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentProposal;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ServiceType;
use App\Services\ProposalSchedulingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProposalFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_accept_proposal_option(): void
    {
        $company = Company::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $company->id]);
        $serviceType = ServiceType::factory()->create(['company_id' => $company->id]);

        $appointment = Appointment::factory()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'service_type_id' => $serviceType->id,
            'status' => AppointmentStatus::Proposed,
        ]);

        $proposal = AppointmentProposal::factory()->create([
            'appointment_id' => $appointment->id,
        ]);

        app(ProposalSchedulingService::class)->acceptProposal($proposal, 2);

        $appointment->refresh();
        $this->assertEquals(AppointmentStatus::Confirmed, $appointment->status);
        $this->assertNotNull($appointment->scheduled_at);
    }

    public function test_public_proposal_page_is_accessible(): void
    {
        $proposal = AppointmentProposal::factory()->create();

        $response = $this->get(route('public.proposals.show', $proposal->token));

        $response->assertOk();
    }
}
