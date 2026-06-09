<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\ProposalSchedulingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessSchedulingJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $companyId,
    ) {}

    public function handle(ProposalSchedulingService $service): void
    {
        $company = Company::findOrFail($this->companyId);
        $service->runForCompany($company);
    }
}
