<?php

namespace App\Services\SchedulingSandbox;

use App\AI\GrokSchedulerService;
use App\Enums\SchedulingSandboxMode;
use App\Enums\SchedulingSandboxRunStatus;
use App\Enums\SchedulingSandboxScenario;
use App\Models\AppointmentProposal;
use App\Models\Company;
use App\Models\SchedulingSandboxRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class SchedulingSandboxService
{
    public function __construct(
        private readonly SchedulingSandboxScenarioSeeder $scenarioSeeder,
        private readonly SchedulingSandboxSnapshotService $snapshotService,
        private readonly SchedulingSandboxValidator $validator,
        private readonly GrokSchedulerService $grokScheduler,
    ) {}

    public function activeRunFor(User $user): ?SchedulingSandboxRun
    {
        return SchedulingSandboxRun::query()
            ->where('created_by_user_id', $user->id)
            ->whereIn('status', [
                SchedulingSandboxRunStatus::Ready,
                SchedulingSandboxRunStatus::Running,
                SchedulingSandboxRunStatus::Completed,
            ])
            ->with(['company', 'sourceCompany', 'messages.proposal'])
            ->latest()
            ->first();
    }

    public function setupScenario(
        User $user,
        SchedulingSandboxScenario $scenario,
        bool $useGrokLive,
    ): SchedulingSandboxRun {
        $this->purgeUserSandboxes($user);

        $useGrokLive = $scenario === SchedulingSandboxScenario::GrokFallback ? false : $useGrokLive;

        return DB::transaction(function () use ($user, $scenario, $useGrokLive) {
            $company = $this->createSandboxCompany($user, '[Lab] Szenario: '.$scenario->label());

            $counts = $this->scenarioSeeder->seed($company, $scenario);

            return SchedulingSandboxRun::create([
                'company_id' => $company->id,
                'created_by_user_id' => $user->id,
                'mode' => SchedulingSandboxMode::Scenario,
                'scenario' => $scenario,
                'status' => SchedulingSandboxRunStatus::Ready,
                'use_grok_live' => $useGrokLive,
                'snapshot_meta' => ['counts' => $counts],
            ]);
        });
    }

    public function setupCompanySnapshot(
        User $user,
        Company $source,
        bool $useGrokLive,
        bool $markDueToday = false,
        bool $anonymizeEmails = true,
    ): SchedulingSandboxRun {
        if ($source->isSandbox()) {
            throw new \InvalidArgumentException('Quellfirma darf keine Sandbox sein.');
        }

        $this->purgeUserSandboxes($user);

        return DB::transaction(function () use ($user, $source, $useGrokLive, $markDueToday, $anonymizeEmails) {
            $company = $this->createSandboxCompany(
                $user,
                '[Lab] '.$source->name,
                $source,
            );

            $meta = $this->snapshotService->copyIntoSandbox(
                $company,
                $source,
                $markDueToday,
                $anonymizeEmails,
            );

            $company->update(['sandbox_snapshot_at' => now()]);

            return SchedulingSandboxRun::create([
                'company_id' => $company->id,
                'created_by_user_id' => $user->id,
                'mode' => SchedulingSandboxMode::CompanySnapshot,
                'source_company_id' => $source->id,
                'status' => SchedulingSandboxRunStatus::Ready,
                'use_grok_live' => $useGrokLive,
                'snapshot_meta' => $meta,
            ]);
        });
    }

    public function runScheduling(SchedulingSandboxRun $run): SchedulingSandboxRun
    {
        $run->update(['status' => SchedulingSandboxRunStatus::Running]);
        SandboxContext::set($run);

        try {
            $company = $run->company;
            $context = $this->grokScheduler->buildContext($company);
            $grokDebug = [
                'use_grok_live' => $run->use_grok_live,
                'context_summary' => [
                    'clusters' => $context->clusters->count(),
                    'staff' => $context->staff->count(),
                ],
                'ai_payload' => $context->toAiPayload(),
            ];

            SandboxJobDispatcher::dispatchScheduling($company->id);

            $proposals = AppointmentProposal::query()
                ->whereHas('appointment', fn ($q) => $q->where('company_id', $company->id))
                ->with(['appointment.customer', 'appointment.serviceType', 'staffMember'])
                ->latest()
                ->get();

            $validationResults = $proposals->map(fn (AppointmentProposal $p) => [
                'proposal_id' => $p->id,
                'customer' => $p->appointment->customer->name,
                'checks' => $this->validator->validateProposals($p),
            ])->values()->all();

            $run->update([
                'status' => SchedulingSandboxRunStatus::Completed,
                'grok_debug' => $grokDebug,
                'validation_results' => $validationResults,
            ]);
        } catch (Throwable $e) {
            $run->update(['status' => SchedulingSandboxRunStatus::Failed]);
            throw $e;
        } finally {
            SandboxContext::set(null);
        }

        return $run->fresh(['company', 'sourceCompany', 'messages.proposal']);
    }

    public function reset(User $user): void
    {
        $this->purgeUserSandboxes($user);
    }

    public function purgeUserSandboxes(User $user): void
    {
        $runs = SchedulingSandboxRun::query()
            ->where('created_by_user_id', $user->id)
            ->with('company')
            ->get();

        foreach ($runs as $run) {
            $run->company?->delete();
            $run->delete();
        }
    }

    private function createSandboxCompany(
        User $user,
        string $name,
        ?Company $source = null,
    ): Company {
        $slug = 'scheduling-lab-user-'.$user->id.'-'.Str::lower(Str::random(6));

        return Company::create([
            'name' => $name,
            'slug' => $slug,
            'email' => "lab-{$user->id}@terminbuddy.test",
            'timezone' => $source?->timezone ?? 'Europe/Berlin',
            'is_active' => true,
            'is_sandbox' => true,
            'sandbox_source_company_id' => $source?->id,
            'sandbox_snapshot_at' => $source ? now() : null,
            'billing_exempt' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function inspectorData(SchedulingSandboxRun $run): array
    {
        $company = $run->company;

        $clusters = app(\App\Services\ClusteringService::class)
            ->clusterDueServices($company)
            ->map(fn ($cluster) => [
                'region' => $cluster->region,
                'jobs' => $cluster->jobs->count(),
                'suggested_date' => $cluster->suggestedDate,
            ])
            ->values()
            ->all();

        return [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'is_sandbox' => true,
                'source_name' => $run->sourceCompany?->name,
                'snapshot_at' => $company->sandbox_snapshot_at?->toIso8601String(),
            ],
            'counts' => [
                'staff' => $company->staffMembers()->count(),
                'customers' => $company->customers()->count(),
                'due_services' => $company->recurringServices()->where('next_due_at', '<=', now())->count(),
                'confirmed_appointments' => $company->appointments()->where('status', 'confirmed')->count(),
            ],
            'clusters' => $clusters,
            'staff' => $company->staffMembers()->with('serviceTypes:id,name')->get()->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'services' => $s->serviceTypes->pluck('name'),
            ]),
            'customers' => $company->customers()->orderBy('postal_code')->limit(20)->get(['id', 'name', 'postal_code', 'email']),
        ];
    }
}
