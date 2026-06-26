<?php

namespace App\Jobs;

use App\Models\ProspectSearchRun;
use App\Services\Prospect\ProspectSearchOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SearchCustomerProspectsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(public int $runId) {}

    public function handle(ProspectSearchOrchestrator $orchestrator): void
    {
        $run = ProspectSearchRun::find($this->runId);

        if ($run) {
            $orchestrator->run($run);
        }
    }
}
