<?php

namespace App\AI;

use App\AI\Prompts\SchedulerSystemPrompt;
use App\DTOs\SchedulingContext;
use App\Models\Appointment;
use App\Models\AppointmentNegotiation;
use App\Models\Company;
use App\Models\StaffMember;
use App\Services\AvailabilityService;
use App\Services\ClusteringService;
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
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function generateProposalsForCompany(Company $company, ?Appointment $negotiationAppointment = null): Collection
    {
        $context = $this->buildContext($company, $negotiationAppointment);

        if (empty(config('grok.api_key'))) {
            return $this->fallbackSchedule($context);
        }

        try {
            $response = GrokAI::chat(
                [
                    ['role' => 'system', 'content' => SchedulerSystemPrompt::build()],
                    ['role' => 'user', 'content' => json_encode($context->toAiPayload(), JSON_THROW_ON_ERROR)],
                ],
                new ChatOptions(temperature: (float) config('grok.default_temperature', 0.3))
            );

            $parsed = json_decode($response->content(), true, 512, JSON_THROW_ON_ERROR);

            return collect($parsed['assignments'] ?? []);
        } catch (GrokException|\JsonException $e) {
            Log::warning('Grok scheduling failed, using fallback', ['error' => $e->getMessage()]);

            return $this->fallbackSchedule($context);
        }
    }

    public function buildContext(Company $company, ?Appointment $negotiationAppointment = null): SchedulingContext
    {
        $clusters = $this->clusteringService->exportClustersForAi($company);
        $staff = $this->exportStaffForAi($company);
        $negotiationFeedback = $this->exportNegotiationFeedback($company, $negotiationAppointment);

        return new SchedulingContext(
            companyId: $company->id,
            clusters: $clusters,
            staff: $staff,
            negotiationFeedback: $negotiationFeedback,
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
                $from = now()->startOfDay();
                $to = now()->addDays(14)->endOfDay();

                return collect([
                    'staff_id' => $staff->id,
                    'qualified_service_type_ids' => $staff->serviceTypes->pluck('id')->values(),
                    'buffer_min' => $staff->buffer_minutes,
                    'weekly_availability' => $staff->availabilities->map(fn ($a) => [
                        'dow' => $a->day_of_week,
                        'start' => substr((string) $a->start_time, 0, 5),
                        'end' => substr((string) $a->end_time, 0, 5),
                    ])->values(),
                    'available_slots' => $this->availabilityService
                        ->exportSlotsForAi($staff, $from, $to, 60)
                        ->take(20)
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

        return $query->get()->map(fn (AppointmentNegotiation $n) => [
            'appointment_id' => $n->appointment_id,
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

        foreach ($context->clusters as $cluster) {
            $jobs = collect($cluster['jobs'] ?? []);
            $staff = $context->staff->first();

            if ($jobs->isEmpty() || ! $staff) {
                continue;
            }

            $staffId = $staff['staff_id'];
            $suggestedDate = $cluster['suggested_date'] ?? now()->addWeekday()->toDateString();
            $base = Carbon::parse($suggestedDate)->setTime(9, 0);

            foreach ($jobs as $job) {
                $assignments->push([
                    'recurring_id' => $job['recurring_id'],
                    'customer_id' => $job['customer_id'],
                    'staff_id' => $staffId,
                    'suggested_date' => $suggestedDate,
                    'slots' => [
                        $base->copy()->toIso8601String(),
                        $base->copy()->addHours(2)->toIso8601String(),
                        $base->copy()->addDay()->setTime(14, 0)->toIso8601String(),
                    ],
                ]);

                $base->addHours(3);
            }
        }

        return $assignments;
    }
}
