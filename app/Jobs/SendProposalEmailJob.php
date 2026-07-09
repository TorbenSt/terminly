<?php

namespace App\Jobs;

use App\Mail\AppointmentProposalMail;
use App\Models\AppointmentProposal;
use App\Models\SchedulingSandboxRun;
use App\Services\SchedulingSandbox\SchedulingSandboxMailRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendProposalEmailJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public AppointmentProposal $proposal,
    ) {}

    public function handle(SchedulingSandboxMailRecorder $recorder): void
    {
        $appointment = $this->proposal->appointment;
        $company = $appointment->company;

        if ($company->isSandbox()) {
            $run = SchedulingSandboxRun::query()
                ->where('company_id', $company->id)
                ->latest()
                ->first();

            if ($run) {
                $recorder->recordProposal($run, $this->proposal);
            }

            $this->proposal->update(['email_sent_at' => now()]);

            return;
        }

        $email = $appointment->customer->email;

        if (! $email) {
            return;
        }

        Mail::to($email)->send(new AppointmentProposalMail($this->proposal));

        $this->proposal->update(['email_sent_at' => now()]);
    }
}
