<?php

namespace App\AI;

use App\AI\Prompts\SchedulerSystemPrompt;
use App\DTOs\SchedulingContext;
use App\Models\Appointment;
use App\Models\AppointmentNegotiation;
use App\Models\Company;
use App\Models\RecurringService;
use App\Models\StaffMember;
use App\Services\AvailabilityService;
use App\Services\ClusteringService;
use App\Services\RegionalRoutingService;
use App\Services\SchedulingSandbox\SandboxContext;
use App\Services\SlotCuratorService;
use Carbon\Carbon;
use GrokPHP\Client\Config\ChatOptions;
use GrokPHP\Client\Exceptions\GrokException;
use GrokPHP\Laravel\Facades\GrokAI;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class GrokSchedulerService
{
    public function __construct(
        private readonly ClusteringService $clusteringService,
        private readonly AvailabilityService $availabilityService,
        private readonly SlotCuratorService $slotCurator,
        private readonly RegionalRoutingService $regionalRouting,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function generateProposalsForCompany(Company $company, ?Appointment $negotiationAppointment = null): Collection
    {
        $context = $this->buildContext($company, $negotiationAppointment);

        if (empty(config('grok.api_key')) || SandboxContext::shouldForceFallback($company)) {
            $assignments = $this->fallbackSchedule($context);
        } else {
            try {
                $response = GrokAI::chat(
                    [
                        ['role' => 'system', 'content' => SchedulerSystemPrompt::build()],
                        ['role' => 'user', 'content' => json_encode($context->toAiPayload(), JSON_THROW_ON_ERROR)],
                    ],
                    new ChatOptions(temperature: (float) config('grok.default_temperature', 0.3))
                );

                $parsed = json_decode($response->content(), true, 512, JSON_THROW_ON_ERROR);

                $assignments = collect($parsed['assignments'] ?? []);
            } catch (GrokException|\JsonException $e) {
                Log::warning('Grok scheduling failed, using fallback', ['error' => $e->getMessage()]);

                $assignments = $this->fallbackSchedule($context);
            }
        }

        return $this->applyNegotiationCuration(
            $this->applyRegionalRouting($assignments, $context),
            $context,
        );
    }

    public function buildContext(Company $company, ?Appointment $negotiationAppointment = null): SchedulingContext
    {
        $clusters = $this->clusteringService->exportClustersForAi($company);
        $staff = $this->exportStaffForAi($company);
        $negotiationFeedback = $this->exportNegotiationFeedback($company, $negotiationAppointment);
        $existingAppointments = $this->regionalRouting->exportAppointmentsForAi($company);

        return new SchedulingContext(
            companyId: $company->id,
            clusters: $clusters,
            staff: $staff,
            negotiationFeedback: $negotiationFeedback,
            existingAppointments: $existingAppointments,
        );
    }

    /**
     * Privacy-friendly staff export: IDs, qualifications, availability windows only.
     */
    public function exportStaffForAi(Company $company): Collection
    {
        return StaffMember::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->with(['serviceTypes:id', 'availabilities'])
            ->get()
            ->map(function (StaffMember $staff) {
                $from = $this->availabilityService->earliestBookableDate();
                $to = now()->addDays((int) config('scheduling.ai_slot_horizon_days', 28))->endOfDay();

                return collect([
                    'staff_id' => $staff->id,
                    'qualified_service_type_ids' => $staff->serviceTypes->pluck('id')->values(),
                    'buffer_min' => $staff->buffer_minutes,
                    'weekly_availability' => $staff->availabilities->map(fn ($a) => array_filter([
                        'dow' => $a->day_of_week,
                        'start' => substr((string) $a->start_time, 0, 5),
                        'end' => substr((string) $a->end_time, 0, 5),
                        'break_start' => $a->break_start_time
                            ? substr((string) $a->break_start_time, 0, 5)
                            : null,
                        'break_end' => $a->break_end_time
                            ? substr((string) $a->break_end_time, 0, 5)
                            : null,
                    ], fn ($value) => $value !== null))->values(),
                    'available_slots' => $this->availabilityService
                        ->exportSlotsForAi($staff, $from, $to, 60)
                        ->take(40)
                        ->values(),
                ]);
            });
    }

    public function exportNegotiationFeedback(Company $company, ?Appointment $appointment = null): Collection
    {
        $query = AppointmentNegotiation::query()
            ->whereHas('appointment', fn ($q) => $q->where('company_id', $company->id))
            ->where('status', 'pending')
            ->latest();

        if ($appointment) {
            $query->where('appointment_id', $appointment->id);
        }

        return $query->with('appointment')->get()->map(fn (AppointmentNegotiation $n) => [
            'appointment_id' => $n->appointment_id,
            'customer_id' => $n->appointment->customer_id,
            'round' => $n->round,
            'feedback' => $n->customer_feedback,
        ]);
    }

    /**
     * Deterministic fallback when Grok API is unavailable (demo/dev).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function fallbackSchedule(SchedulingContext $context): Collection
    {
        $assignments = collect();
        $feedbackByCustomer = $context->negotiationFeedback->keyBy('customer_id');

        foreach ($context->clusters as $cluster) {
            $jobs = collect($cluster['jobs'] ?? []);

            if ($jobs->isEmpty()) {
                continue;
            }

            $suggestedDate = $cluster['suggested_date'] ?? $this->availabilityService->earliestBookableDate()->toDateString();
            $base = Carbon::parse($suggestedDate)->setTime(9, 0);
            if ($base->lt($this->availabilityService->earliestBookableDate())) {
                $base = $this->availabilityService->earliestBookableDate()->setTime(9, 0);
            }

            foreach ($jobs as $job) {
                $staff = $this->pickQualifiedStaff($context->staff, $job['service_type_id'] ?? null);

                if (! $staff) {
                    continue;
                }

                $staffId = $staff['staff_id'];
                $customerId = $job['customer_id'];
                $feedback = $feedbackByCustomer->get($customerId);
                $durationMinutes = $job['duration_min'] ?? 60;

                if ($feedback && filled($feedback['feedback'] ?? null)) {
                    $curated = $this->curateNegotiationSlots(
                        $staffId,
                        $feedback['feedback'],
                        $durationMinutes,
                        $job['postal_code'] ?? null,
                    );
                    $slots = $curated['slots'];
                    $recommendedOption = $curated['recommended_option'];
                } else {
                    $staffMember = StaffMember::find($staffId);
                    $postalCode = $job['postal_code'] ?? null;

                    if ($staffMember && $postalCode) {
                        $slots = $this->regionalRouting->buildRegionalSlots(
                            $staffMember,
                            $postalCode,
                            $durationMinutes,
                        );
                    } else {
                        $slots = [
                            $base->copy()->toIso8601String(),
                            $base->copy()->addHours(2)->toIso8601String(),
                            $base->copy()->addDay()->setTime(14, 0)->toIso8601String(),
                        ];
                        $base->addHours(3);
                    }

                    $recommendedOption = null;
                }

                $assignments->push([
                    'recurring_id' => $job['recurring_id'],
                    'customer_id' => $customerId,
                    'staff_id' => $staffId,
                    'suggested_date' => Carbon::parse($slots[0])->toDateString(),
                    'slots' => $slots,
                    'recommended_option' => $recommendedOption,
                ]);
            }
        }

        return $assignments;
    }

    /**
     * @param  Collection<int, array<string, mixed>|Collection<string, mixed>>  $staffPool
     * @return array<string, mixed>|null
     */
    private function pickQualifiedStaff(Collection $staffPool, mixed $serviceTypeId): ?array
    {
        $normalized = $staffPool
            ->map(fn ($staff) => $staff instanceof Collection ? $staff->all() : (array) $staff)
            ->values();

        if ($serviceTypeId === null) {
            return $normalized->first();
        }

        $serviceTypeId = (int) $serviceTypeId;

        return $normalized->first(function (array $staff) use ($serviceTypeId) {
            $ids = collect($staff['qualified_service_type_ids'] ?? [])->map(fn ($id) => (int) $id);

            return $ids->contains($serviceTypeId);
        }) ?? $normalized->first();
    }

    /**
     * @return array{slots: list<string>, recommended_option: int}
     */
    private function curateNegotiationSlots(
        int $staffId,
        string $feedback,
        int $durationMinutes,
        ?string $postalCode = null,
    ): array {
        $staff = StaffMember::find($staffId);

        if (! $staff) {
            return [
                'slots' => [
                    now()->addWeek()->setTime(9, 0)->toIso8601String(),
                    now()->addWeek()->setTime(11, 0)->toIso8601String(),
                    now()->addWeeks(2)->setTime(9, 0)->toIso8601String(),
                ],
                'recommended_option' => 1,
            ];
        }

        $curated = $this->slotCurator->curate($staff, $feedback, $durationMinutes, $postalCode);

        return [
            'slots' => $curated['slots'],
            'recommended_option' => $curated['recommended_index'] + 1,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $assignments
     * @return Collection<int, array<string, mixed>>
     */
    private function applyRegionalRouting(Collection $assignments, SchedulingContext $context): Collection
    {
        $feedbackByCustomer = $context->negotiationFeedback->keyBy('customer_id');
        $postalByCustomer = $this->postalCodesByCustomer($context);

        return $assignments->map(function (array $assignment) use ($feedbackByCustomer, $postalByCustomer) {
            $customerId = $assignment['customer_id'] ?? null;
            $feedback = $customerId ? $feedbackByCustomer->get($customerId) : null;

            if ($feedback && filled($feedback['feedback'] ?? null)) {
                return $assignment;
            }

            $postalCode = $customerId ? ($postalByCustomer[$customerId] ?? null) : null;
            $staff = StaffMember::find($assignment['staff_id'] ?? null);

            if (! $staff || ! $postalCode) {
                return $assignment;
            }

            $durationMinutes = $this->resolveDurationMinutes($assignment);
            $slots = $this->regionalRouting->buildRegionalSlots($staff, $postalCode, $durationMinutes);

            $assignment['slots'] = $slots;
            $assignment['suggested_date'] = Carbon::parse($slots[0])->toDateString();

            return $assignment;
        });
    }

    /**
     * @return array<int, string>
     */
    private function postalCodesByCustomer(SchedulingContext $context): array
    {
        $map = [];

        foreach ($context->clusters as $cluster) {
            foreach ($cluster['jobs'] ?? [] as $job) {
                if (isset($job['customer_id'], $job['postal_code'])) {
                    $map[$job['customer_id']] = $job['postal_code'];
                }
            }
        }

        return $map;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $assignments
     * @return Collection<int, array<string, mixed>>
     */
    private function applyNegotiationCuration(Collection $assignments, SchedulingContext $context): Collection
    {
        $feedbackByCustomer = $context->negotiationFeedback->keyBy('customer_id');
        $postalByCustomer = $this->postalCodesByCustomer($context);

        return $assignments->map(function (array $assignment) use ($feedbackByCustomer, $postalByCustomer) {
            $customerId = $assignment['customer_id'] ?? null;
            $feedback = $customerId ? $feedbackByCustomer->get($customerId) : null;

            if (! $feedback || blank($feedback['feedback'] ?? null)) {
                return $assignment;
            }

            $staff = StaffMember::find($assignment['staff_id'] ?? null);
            if (! $staff) {
                return $assignment;
            }

            $durationMinutes = $this->resolveDurationMinutes($assignment);
            $postalCode = $customerId ? ($postalByCustomer[$customerId] ?? null) : null;
            $curated = $this->slotCurator->curateFromIsoSlots(
                $staff,
                $assignment['slots'] ?? [],
                $feedback['feedback'],
                $durationMinutes,
                $postalCode,
            );

            $assignment['slots'] = $curated['slots'];
            $assignment['recommended_option'] = $curated['recommended_index'] + 1;
            $assignment['suggested_date'] = Carbon::parse($curated['slots'][0])->toDateString();

            return $assignment;
        });
    }

    /**
     * @param  array<string, mixed>  $assignment
     */
    private function resolveDurationMinutes(array $assignment): int
    {
        if (isset($assignment['recurring_id'])) {
            $recurring = RecurringService::find($assignment['recurring_id']);

            if ($recurring?->serviceType) {
                return $recurring->serviceType->duration_minutes;
            }
        }

        return 60;
    }
}
