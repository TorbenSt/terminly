<?php

namespace Tests\Feature;

use App\Enums\NegotiationStatus;
use App\Enums\SchedulingSandboxScenario;
use App\Models\AppointmentProposal;
use App\Models\User;
use App\Services\ProposalSchedulingService;
use App\Services\SchedulingSandbox\SchedulingSandboxService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NegotiationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        config(['scheduling_lab.enabled' => true]);
    }

    private function createSuperAdmin(): User
    {
        $user = User::factory()->create(['company_id' => null]);
        $user->assignRole('super_admin');

        return $user;
    }

    public function test_negotiation_feedback_generates_new_proposal_with_single_inbox_message(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00', 'Europe/Berlin'));

        $user = $this->createSuperAdmin();
        $sandbox = app(SchedulingSandboxService::class);
        $sandbox->setupScenario($user, SchedulingSandboxScenario::SimpleMaintenance, false);
        $run = $sandbox->activeRunFor($user);

        $sandbox->runScheduling($run);
        $run->refresh();

        $initialMessageCount = $run->messages()->count();
        $this->assertSame(1, $initialMessageCount);

        $initialProposal = AppointmentProposal::query()
            ->whereHas('appointment', fn ($q) => $q->where('company_id', $run->company_id))
            ->firstOrFail();

        $initialOptions = collect($initialProposal->options())->map->toIso8601String()->all();

        $negotiation = app(ProposalSchedulingService::class)->rejectAllOptions($initialProposal);

        $this->actingAs($user)
            ->post(route('public.negotiations.store', $negotiation->token), [
                'feedback' => 'Gerne Montag vormittags in einer Woche, am besten gegen 9 Uhr.',
            ])
            ->assertRedirect();

        $run->refresh();
        $negotiation->refresh();

        $this->assertEquals(NegotiationStatus::Processed, $negotiation->status);
        $this->assertSame(2, $run->messages()->count(), 'Expected exactly one new inbox message after negotiation');

        $newProposal = AppointmentProposal::query()
            ->where('appointment_id', $initialProposal->appointment_id)
            ->where('id', '!=', $initialProposal->id)
            ->latest()
            ->firstOrFail();

        $newOptions = collect($newProposal->options())->map->toIso8601String()->all();

        $this->assertNotEquals($initialOptions, $newOptions, 'Negotiation should produce different slot options');
        $this->assertNotNull($newProposal->recommended_option);

        $slotDates = collect($newProposal->options())->sortBy(fn ($slot) => $slot->timestamp)->values();
        $storedDates = collect($newProposal->options())->values();

        $this->assertTrue($storedDates->every(fn ($slot, int $index) => $slot->eq($slotDates[$index])));
        $uniqueDays = $slotDates->map(fn ($slot) => $slot->toDateString())->unique();

        $this->assertGreaterThanOrEqual(
            2,
            $uniqueDays->count(),
            'Narrow preference should spread slots across at least two calendar days',
        );

        $sameDaySlots = $slotDates->groupBy(fn ($slot) => $slot->toDateString());
        foreach ($sameDaySlots as $daySlots) {
            if ($daySlots->count() < 2) {
                continue;
            }
            $min = $daySlots->min();
            $max = $daySlots->max();
            $this->assertGreaterThanOrEqual(
                2,
                $min->diffInHours($max),
                'Same-day slots must be at least 2 hours apart',
            );
        }

        foreach ($newProposal->options() as $slot) {
            $this->assertSame(Carbon::MONDAY, $slot->dayOfWeek, 'Slots should honor Monday preference');
            $this->assertLessThan(12, $slot->hour, 'Slots should honor morning preference');
        }

        Carbon::setTestNow();
    }
}
