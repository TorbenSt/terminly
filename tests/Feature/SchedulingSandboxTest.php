<?php

namespace Tests\Feature;

use App\Enums\SchedulingSandboxRunStatus;
use App\Enums\SchedulingSandboxScenario;
use App\Models\AppointmentProposal;
use App\Models\Company;
use App\Models\User;
use App\Services\SchedulingSandbox\SchedulingSandboxService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulingSandboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        config(['scheduling_lab.enabled' => true]);
    }

    protected function createSuperAdmin(): User
    {
        $user = User::factory()->create(['company_id' => null]);
        $user->assignRole('super_admin');

        return $user;
    }

    public function test_scheduling_lab_requires_feature_flag(): void
    {
        config(['scheduling_lab.enabled' => false]);

        $this->actingAs($this->createSuperAdmin())
            ->get(route('admin.scheduling-lab.index'))
            ->assertNotFound();
    }

    public function test_super_admin_can_access_scheduling_lab(): void
    {
        $this->actingAs($this->createSuperAdmin())
            ->get(route('admin.scheduling-lab.index'))
            ->assertOk();
    }

    public function test_scenario_setup_creates_sandbox_company_and_run(): void
    {
        $user = $this->createSuperAdmin();

        $this->actingAs($user)
            ->post(route('admin.scheduling-lab.scenario'), [
                'scenario' => SchedulingSandboxScenario::SimpleMaintenance->value,
                'use_grok_live' => false,
            ])
            ->assertRedirect();

        $run = app(SchedulingSandboxService::class)->activeRunFor($user);

        $this->assertNotNull($run);
        $this->assertTrue($run->company->isSandbox());
        $this->assertEquals(SchedulingSandboxRunStatus::Ready, $run->status);
        $this->assertSame(1, $run->company->staffMembers()->count());
    }

    public function test_run_scheduling_creates_inbox_message_without_real_email(): void
    {
        $user = $this->createSuperAdmin();
        $service = app(SchedulingSandboxService::class);

        $service->setupScenario($user, SchedulingSandboxScenario::SimpleMaintenance, false);
        $run = $service->activeRunFor($user);

        $service->runScheduling($run);

        $run->refresh();

        $this->assertEquals(SchedulingSandboxRunStatus::Completed, $run->status);
        $this->assertGreaterThan(0, $run->messages()->count());
        $this->assertNotEmpty($run->validation_results);
    }

    public function test_company_snapshot_does_not_modify_source_company(): void
    {
        $user = $this->createSuperAdmin();
        $source = Company::factory()->create(['is_sandbox' => false, 'billing_exempt' => true]);

        \App\Models\Customer::factory()->count(2)->create(['company_id' => $source->id]);

        $customerCountBefore = $source->customers()->count();

        $this->actingAs($user)
            ->post(route('admin.scheduling-lab.snapshot'), [
                'company_id' => $source->id,
                'use_grok_live' => false,
                'mark_due_today' => true,
                'anonymize_emails' => true,
            ])
            ->assertRedirect();

        $this->assertSame($customerCountBefore, $source->fresh()->customers()->count());

        $run = app(SchedulingSandboxService::class)->activeRunFor($user);
        $this->assertNotNull($run);
        $this->assertTrue($run->company->isSandbox());
        $this->assertEquals($source->id, $run->source_company_id);
    }

    public function test_reset_removes_sandbox_data(): void
    {
        $user = $this->createSuperAdmin();
        $service = app(SchedulingSandboxService::class);
        $service->setupScenario($user, SchedulingSandboxScenario::SimpleMaintenance, false);

        $this->assertNotNull($service->activeRunFor($user));

        $this->actingAs($user)
            ->post(route('admin.scheduling-lab.reset'))
            ->assertRedirect();

        $this->assertNull($service->activeRunFor($user));
        $this->assertSame(0, Company::query()->where('is_sandbox', true)->count());
    }

    public function test_super_admin_can_view_sandbox_staff_calendar(): void
    {
        $user = $this->createSuperAdmin();
        $service = app(SchedulingSandboxService::class);
        $service->setupScenario($user, SchedulingSandboxScenario::RegionalTour, false);
        $staff = $service->activeRunFor($user)->company->staffMembers()->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.scheduling-lab.staff-calendar', $staff))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/SchedulingLab/StaffCalendar')
                ->has('appointments')
                ->has('proposalOptions')
                ->where('staffMember.id', $staff->id));
    }

    public function test_regional_tour_scheduling_prefers_existing_cluster_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00', 'Europe/Berlin'));

        $user = $this->createSuperAdmin();
        $service = app(SchedulingSandboxService::class);
        $service->setupScenario($user, SchedulingSandboxScenario::RegionalTour, false);
        $run = $service->activeRunFor($user);

        $service->runScheduling($run);
        $run->refresh();

        $berlinTourDate = now()->addWeek()->startOfDay();
        while ($berlinTourDate->dayOfWeek !== Carbon::TUESDAY) {
            $berlinTourDate->addDay();
        }

        $validation = collect($run->validation_results)->first();
        $this->assertNotNull($validation);

        $routingCheck = collect($validation['checks'])->firstWhere('key', 'regional_routing');
        $this->assertNotNull($routingCheck);
        $this->assertTrue($routingCheck['passed'], $routingCheck['detail'] ?? 'Regional routing failed');

        $proposalSlots = AppointmentProposal::query()
            ->whereHas('appointment', fn ($q) => $q->where('company_id', $run->company_id))
            ->latest()
            ->firstOrFail()
            ->options();

        $onBerlinDay = collect($proposalSlots)
            ->filter()
            ->filter(fn (Carbon $slot) => $slot->toDateString() === $berlinTourDate->toDateString())
            ->count();

        $this->assertGreaterThanOrEqual(2, $onBerlinDay);

        Carbon::setTestNow();
    }

    public function test_real_life_capacity_scenario_seeds_dense_calendars_and_finds_slots(): void
    {
        // Keep app timezone (UTC) to match AvailabilityService / Eloquent casting in tests.
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00', config('app.timezone')));

        $user = $this->createSuperAdmin();
        $service = app(SchedulingSandboxService::class);
        $service->setupScenario($user, SchedulingSandboxScenario::RealLifeCapacity, false);
        $run = $service->activeRunFor($user);

        $company = $run->company;
        $this->assertSame(5, $company->staffMembers()->count());
        $this->assertSame(1, $company->recurringServices()->where('next_due_at', '<=', now())->count());

        $maintenanceId = $company->serviceTypes()->where('name', 'Wartung')->value('id');
        $qualifiedCount = $company->staffMembers()
            ->whereHas('serviceTypes', fn ($q) => $q->where('service_types.id', $maintenanceId))
            ->count();
        $this->assertSame(2, $qualifiedCount);

        $plzPrefixes = $company->customers()
            ->pluck('postal_code')
            ->map(fn (string $plz) => substr($plz, 0, 3))
            ->unique()
            ->count();
        $this->assertGreaterThanOrEqual(6, $plzPrefixes);

        $confirmed = $company->appointments()->where('status', 'confirmed')->count();
        $this->assertGreaterThan(500, $confirmed);

        $counts = $run->snapshot_meta['counts'] ?? [];
        $this->assertSame(5, $counts['staff'] ?? null);
        $this->assertGreaterThanOrEqual(6, $counts['plz_clusters'] ?? 0);

        $service->runScheduling($run);
        $run->refresh();

        $this->assertEquals(SchedulingSandboxRunStatus::Completed, $run->status);

        $proposal = AppointmentProposal::query()
            ->whereHas('appointment', fn ($q) => $q->where('company_id', $company->id))
            ->with(['staffMember.serviceTypes', 'appointment.serviceType'])
            ->latest()
            ->firstOrFail();

        $this->assertTrue(
            $proposal->staffMember->serviceTypes->contains('id', $proposal->appointment->service_type_id),
            'Proposal must be assigned to a qualified technician',
        );

        $validation = collect($run->validation_results)->first();
        $this->assertNotNull($validation);

        $qualification = collect($validation['checks'])->firstWhere('key', 'qualification');
        $this->assertTrue($qualification['passed'] ?? false, $qualification['detail'] ?? 'qualification failed');

        $complete = collect($validation['checks'])->firstWhere('key', 'proposal_complete');
        $this->assertTrue($complete['passed'] ?? false, $complete['detail'] ?? 'proposal incomplete');

        foreach ($validation['checks'] as $check) {
            if (! str_starts_with($check['key'], 'slot_') || ! str_ends_with($check['key'], '_availability')) {
                continue;
            }

            $this->assertTrue($check['passed'], $check['detail'] ?? $check['key']);
        }

        Carbon::setTestNow();
    }
}
