<?php

namespace App\Jobs;

use App\Enums\ProspectFeedbackAction;
use App\Enums\ProspectStatus;
use App\Mail\ProspectOutreachMail;
use App\Models\CustomerProspect;
use App\Models\ProspectOutreachEmail;
use App\Services\Prospect\ProspectFeedbackService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendProspectOutreachJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $prospectId,
        public string $subject,
        public string $body,
    ) {}

    public function handle(ProspectFeedbackService $feedback): void
    {
        $prospect = CustomerProspect::with('company')->find($this->prospectId);

        if (! $prospect || ! $prospect->canReceiveOutreach()) {
            return;
        }

        $log = ProspectOutreachEmail::create([
            'company_id' => $prospect->company_id,
            'customer_prospect_id' => $prospect->id,
            'subject' => $this->subject,
            'body_snapshot' => $this->body,
            'status' => 'queued',
        ]);

        try {
            Mail::to($prospect->email)->send(new ProspectOutreachMail($prospect, $prospect->company, $this->body));

            $log->update(['status' => 'sent', 'sent_at' => now()]);
            $prospect->update([
                'status' => ProspectStatus::Contacted,
                'last_contacted_at' => now(),
                'contact_count' => $prospect->contact_count + 1,
            ]);

            $feedback->record(
                $prospect->company,
                ProspectFeedbackAction::EmailSent,
                $prospect,
                $prospect->run,
            );
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
        }
    }
}
