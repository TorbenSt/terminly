<?php

namespace App\Services;

use App\AI\GrokSchedulerService;
use App\Enums\AppointmentStatus;
use App\Enums\NegotiationStatus;
use App\Models\SchedulingSandboxRun;
use App\Services\SchedulingSandbox\SchedulingSandboxMailRecorder;
use App\Services\SchedulingSandbox\SandboxJobDispatcher;
use App\Models\Appointment;
use App\Models\AppointmentNegotiation;
use App\Models\AppointmentProposal;
use App\Models\Company;
use App\Models\RecurringService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProposalSchedulingService
{
    public function __construct(
        private readonly GrokSchedulerService $grokScheduler,
    ) {}

    public function runForCompany(Company $company): Collection
    {
        $assignments = $this->grokScheduler->generateProposalsForCompany($company);

        return $assignments->map(fn (array $assignment) => $this->createProposalFromAssignment($company, $assignment));
    }

    public function runForNegotiation(AppointmentNegotiation $negotiation): ?AppointmentProposal
    {
        $appointment = $negotiation->appointment;
        $assignments = $this->grokScheduler->generateProposalsForCompany(
            $appointment->company,
            $appointment
        );

        $assignment = $assignments->firstWhere('customer_id', $appointment->customer_id)
            ?? $assignments->first();

        if (! $assignment) {
            return null;
        }

        $negotiation->update([
            'status' => NegotiationStatus::Processed,
            'processed_at' => now(),
        ]);

        $proposal = $this->createProposalFromAssignment($appointment->company, $assignment, $appointment);
        SandboxJobDispatcher::dispatchProposalEmail($proposal);

        return $proposal;
    }

    public function createProposalFromAssignment(
        Company $company,
        array $assignment,
        ?Appointment $existingAppointment = null,
    ): AppointmentProposal {
        return DB::transaction(function () use ($company, $assignment, $existingAppointment) {
            $recurring = isset($assignment['recurring_id'])
                ? RecurringService::find($assignment['recurring_id'])
                : null;

            $customerId = $assignment['customer_id'] ?? $recurring?->customer_id;
            $serviceTypeId = $recurring?->service_type_id ?? $existingAppointment?->service_type_id;

            $appointment = $existingAppointment ?? Appointment::create([
                'company_id' => $company->id,
                'customer_id' => $customerId,
                'service_type_id' => $serviceTypeId,
                'staff_member_id' => $assignment['staff_id'] ?? null,
                'recurring_service_id' => $recurring?->id,
                'status' => AppointmentStatus::Proposed,
                'duration_minutes' => $recurring?->serviceType->duration_minutes ?? 60,
                'travel_time_minutes' => 15,
            ]);

            if ($existingAppointment) {
                $appointment->update([
                    'staff_member_id' => $assignment['staff_id'] ?? $appointment->staff_member_id,
                    'status' => AppointmentStatus::Proposed,
                    'negotiation_round' => $appointment->negotiation_round + 1,
                ]);
            }

            $round = ($appointment->proposals()->max('round') ?? 0) + 1;
            $slots = collect($assignment['slots'] ?? [])->take(3)->values();

            $proposal = AppointmentProposal::create([
                'appointment_id' => $appointment->id,
                'round' => $round,
                'option_1_at' => Carbon::parse($slots[0] ?? now()->addDay()),
                'option_2_at' => Carbon::parse($slots[1] ?? now()->addDays(2)),
                'option_3_at' => Carbon::parse($slots[2] ?? now()->addDays(3)),
                'staff_member_id' => $assignment['staff_id'] ?? null,
            ]);

            SandboxJobDispatcher::dispatchProposalEmail($proposal);

            return $proposal;
        });
    }

    public function acceptProposal(AppointmentProposal $proposal, int $option): Appointment
    {
        $appointment = $proposal->appointment;
        $slots = $proposal->options();
        $scheduledAt = $slots[$option] ?? $proposal->option_1_at;

        $proposal->update([
            'selected_option' => $option,
            'responded_at' => now(),
        ]);

        $appointment->update([
            'status' => AppointmentStatus::Confirmed,
            'scheduled_at' => $scheduledAt,
            'staff_member_id' => $proposal->staff_member_id ?? $appointment->staff_member_id,
        ]);

        if ($appointment->recurring_service_id) {
            $appointment->recurringService?->update([
                'last_completed_at' => null,
            ]);
        }

        return $appointment->fresh();
    }

    public function rejectAllOptions(AppointmentProposal $proposal): AppointmentNegotiation
    {
        $proposal->update(['responded_at' => now()]);
        $appointment = $proposal->appointment;
        $round = $appointment->negotiation_round + 1;

        if ($round > 2) {
            return $this->escalateToManualNegotiation($appointment, 'Kunde hat alle Vorschläge nach 2 Runden abgelehnt.');
        }

        return AppointmentNegotiation::create([
            'appointment_id' => $appointment->id,
            'round' => $round,
            'customer_feedback' => '',
            'status' => NegotiationStatus::Pending,
        ]);
    }

    public function submitNegotiationFeedback(AppointmentNegotiation $negotiation, string $feedback): AppointmentNegotiation
    {
        $negotiation->update([
            'customer_feedback' => $feedback,
        ]);

        $appointment = $negotiation->appointment;
        $appointment->update(['status' => AppointmentStatus::Negotiation]);

        if ($negotiation->round >= 2) {
            return $this->escalateToManualNegotiation(
                $appointment,
                "Kundenwunsch nach Runde {$negotiation->round}: {$feedback}"
            );
        }

        SandboxJobDispatcher::dispatchNegotiation($negotiation);

        return $negotiation->fresh();
    }

    public function escalateToManualNegotiation(Appointment $appointment, string $summary): AppointmentNegotiation
    {
        $appointment->update(['status' => AppointmentStatus::Negotiation]);

        $negotiation = AppointmentNegotiation::create([
            'appointment_id' => $appointment->id,
            'round' => $appointment->negotiation_round + 1,
            'customer_feedback' => $summary,
            'ai_summary' => $this->buildWhatsAppSummary($appointment, $summary),
            'status' => NegotiationStatus::Escalated,
            'processed_at' => now(),
        ]);

        if ($appointment->company->isSandbox()) {
            $run = SchedulingSandboxRun::query()
                ->where('company_id', $appointment->company_id)
                ->latest()
                ->first();

            if ($run) {
                app(SchedulingSandboxMailRecorder::class)->recordEscalation(
                    $run,
                    $appointment,
                    $negotiation->ai_summary ?? $summary,
                );
            }
        }

        return $negotiation;
    }

    public function buildWhatsAppSummary(Appointment $appointment, string $context): string
    {
        $customer = $appointment->customer;
        $service = $appointment->serviceType;

        return implode("\n", [
            '📋 *Manuelle Terminverhandlung*',
            "Kunde: {$customer->name}",
            "Tel: {$customer->phone}",
            "PLZ: {$customer->postal_code}",
            "Service: {$service->name} ({$service->duration_minutes} Min.)",
            "Runde: {$appointment->negotiation_round}",
            "Kontext: {$context}",
        ]);
    }
}
