<?php

namespace App\Mail;

use App\Models\AppointmentProposal;
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
        $proposal = $this->proposal->loadMissing('appointment.negotiations');

        return new Content(
            markdown: 'emails.appointment-proposal',
            with: [
                'proposal' => $proposal,
                'appointment' => $proposal->appointment,
                'responseUrl' => route('public.proposals.show', $proposal->token),
                'negotiationFeedback' => $proposal->negotiationFeedback(),
            ],
        );
    }
}
