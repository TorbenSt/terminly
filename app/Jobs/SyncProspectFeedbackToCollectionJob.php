<?php

namespace App\Jobs;

use App\Models\ProspectFeedback;
use App\Services\Prospect\ProspectFeedbackRagService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncProspectFeedbackToCollectionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $feedbackId) {}

    public function handle(ProspectFeedbackRagService $rag): void
    {
        $feedback = ProspectFeedback::find($this->feedbackId);

        if (! $feedback || $feedback->grok_document_id) {
            return;
        }

        $rag->syncFeedback($feedback);
    }
}
