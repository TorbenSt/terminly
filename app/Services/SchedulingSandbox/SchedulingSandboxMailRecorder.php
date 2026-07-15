<?php

namespace App\Services\SchedulingSandbox;

use App\Enums\SchedulingSandboxMessageType;
use App\Mail\AppointmentProposalMail;
use App\Models\Appointment;
use App\Models\AppointmentProposal;
use App\Services\ArrivalWindowService;
use App\Models\SchedulingSandboxMessage;
use App\Models\SchedulingSandboxRun;

class SchedulingSandboxMailRecorder
{
    public function recordProposal(SchedulingSandboxRun $run, AppointmentProposal $proposal): SchedulingSandboxMessage
    {
        $proposal->load(['appointment.customer', 'appointment.serviceType', 'staffMember']);
        $mailable = new AppointmentProposalMail($proposal);
        $html = $mailable->render();

        return SchedulingSandboxMessage::create([
            'scheduling_sandbox_run_id' => $run->id,
            'appointment_proposal_id' => $proposal->id,
            'type' => SchedulingSandboxMessageType::Proposal,
            'subject' => $mailable->envelope()->subject,
            'body_html' => $html,
            'meta' => $this->proposalMeta($proposal),
        ]);
    }

    public function recordEscalation(SchedulingSandboxRun $run, Appointment $appointment, string $summary): SchedulingSandboxMessage
    {
        $appointment->load(['customer', 'serviceType']);

        $body = nl2br(e($summary));

        return SchedulingSandboxMessage::create([
            'scheduling_sandbox_run_id' => $run->id,
            'type' => SchedulingSandboxMessageType::Escalation,
            'subject' => 'Manuelle Terminverhandlung – Eskalation',
            'body_html' => '<pre style="white-space:pre-wrap;font-family:inherit">'.$body.'</pre>',
            'meta' => [
                'appointment_id' => $appointment->id,
                'customer_name' => $appointment->customer->name,
                'postal_code' => $appointment->customer->postal_code,
                'service_name' => $appointment->serviceType->name,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function proposalMeta(AppointmentProposal $proposal): array
    {
        $appointment = $proposal->appointment;
        $arrivalWindows = app(ArrivalWindowService::class)->forProposal($proposal);
        $formatter = app(ArrivalWindowService::class);

        return [
            'proposal_token' => $proposal->token,
            'customer_name' => $appointment->customer->name,
            'customer_postal_code' => $appointment->customer->postal_code,
            'service_name' => $appointment->serviceType->name,
            'staff_name' => $proposal->staffMember?->name,
            'options' => collect($proposal->options())->map(function ($at, $n) use ($arrivalWindows, $formatter, $appointment) {
                return [
                    'number' => $n,
                    'iso' => $at->toIso8601String(),
                    'label' => isset($arrivalWindows[$n])
                        ? $formatter->formatLabel($arrivalWindows[$n], $appointment->company)
                        : null,
                ];
            })->values()->all(),
            'public_url' => route('public.proposals.show', $proposal->token),
        ];
    }
}
