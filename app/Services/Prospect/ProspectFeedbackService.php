<?php

namespace App\Services\Prospect;

use App\Enums\ProspectFeedbackAction;
use App\Jobs\SyncProspectFeedbackToCollectionJob;
use App\Models\Company;
use App\Models\CustomerProspect;
use App\Models\ProspectFeedback;
use App\Models\ProspectSearchRun;

class ProspectFeedbackService
{
    public function record(
        Company $company,
        ProspectFeedbackAction $action,
        ?CustomerProspect $prospect = null,
        ?ProspectSearchRun $run = null,
        ?string $reason = null,
        array $metadata = [],
    ): ProspectFeedback {
        $feedback = ProspectFeedback::create([
            'company_id' => $company->id,
            'customer_prospect_id' => $prospect?->id,
            'prospect_search_run_id' => $run?->id,
            'action' => $action,
            'reason' => $reason,
            'metadata' => $metadata,
        ]);

        SyncProspectFeedbackToCollectionJob::dispatch($feedback->id);

        return $feedback;
    }
}
