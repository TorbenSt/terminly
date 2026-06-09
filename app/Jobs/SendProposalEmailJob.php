<?php

namespace App\Jobs;

use App\Mail\AppointmentProposalMail;
use App\Models\AppointmentProposal;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendProposalEmailJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public AppointmentProposal $proposal,
    ) {}

    public function handle(): void
    {
        $appointment = $this->proposal->appointment;
        $email = $appointment->customer->email;

        if (! $email) {
            return;
        }

        Mail::to($email)->send(new AppointmentProposalMail($this->proposal));

        $this->proposal->update(['email_sent_at' => now()]);
    }
}
