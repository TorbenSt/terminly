<?php

namespace App\Jobs;

use App\Models\AppointmentNegotiation;
use App\Services\ProposalSchedulingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessNegotiationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public AppointmentNegotiation $negotiation,
    ) {}

    public function handle(ProposalSchedulingService $service): void
    {
        $service->runForNegotiation($this->negotiation);
    }
}
