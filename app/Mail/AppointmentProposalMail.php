<?php

namespace App\Mail;

use App\Models\AppointmentProposal;
use App\Services\ArrivalWindowService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentProposalMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AppointmentProposal $proposal,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ihre Terminvorschläge – bitte wählen Sie eine Option',
        );
    }

    public function content(): Content
    {
        $proposal = $this->proposal->loadMissing(['appointment.negotiations', 'appointment.company', 'staffMember']);
        $formatter = app(ArrivalWindowService::class);
        $arrivalWindows = $formatter->forProposal($proposal);
        $optionLabels = collect($proposal->options())->mapWithKeys(function ($slot, $number) use ($arrivalWindows, $formatter, $proposal) {
            if (! $slot) {
                return [];
            }

            $label = isset($arrivalWindows[$number])
                ? $formatter->formatLabel($arrivalWindows[$number], $proposal->appointment->company)
                : $slot->timezone($proposal->appointment->company->timezone)->format('d.m.Y H:i').' Uhr';

            return [$number => $label];
        });

        return new Content(
            markdown: 'emails.appointment-proposal',
            with: [
                'proposal' => $proposal,
                'appointment' => $proposal->appointment,
                'optionLabels' => $optionLabels,
                'responseUrl' => route('public.proposals.show', $proposal->token),
                'negotiationFeedback' => $proposal->negotiationFeedback(),
            ],
        );
    }
}
