<?php

namespace App\Services\SchedulingSandbox;

use App\Jobs\ProcessNegotiationJob;
use App\Jobs\ProcessSchedulingJob;
use App\Jobs\SendProposalEmailJob;
use App\Models\AppointmentNegotiation;
use App\Models\AppointmentProposal;
use App\Models\Company;

class SandboxJobDispatcher
{
    public static function dispatchScheduling(int $companyId): void
    {
        $company = Company::findOrFail($companyId);

        if ($company->isSandbox()) {
            ProcessSchedulingJob::dispatchSync($companyId);
        } else {
            ProcessSchedulingJob::dispatch($companyId);
        }
    }

    public static function dispatchNegotiation(AppointmentNegotiation $negotiation): void
    {
        $company = $negotiation->appointment->company;

        if ($company->isSandbox()) {
            ProcessNegotiationJob::dispatchSync($negotiation);
        } else {
            ProcessNegotiationJob::dispatch($negotiation);
        }
    }

    public static function dispatchProposalEmail(AppointmentProposal $proposal): void
    {
        $company = $proposal->appointment->company;

        if ($company->isSandbox()) {
            SendProposalEmailJob::dispatchSync($proposal);
        } else {
            SendProposalEmailJob::dispatch($proposal);
        }
    }
}
