<?php

namespace App\Services\Prospect;

use App\Jobs\SearchCustomerProspectsJob;

class ProspectSearchDispatcher
{
    /**
     * Manual searches run after the HTTP response via the sync driver by default in local
     * so they work without a queue worker. Scheduled/cron jobs use the default queue.
     */
    public static function dispatch(int $runId, bool $manual = false): void
    {
        $pending = SearchCustomerProspectsJob::dispatch($runId);

        $runAfterResponse = $manual && (
            config('prospect_search.dispatch_after_response')
            || app()->environment('local')
        );

        if ($runAfterResponse) {
            $pending->afterResponse()->onConnection('sync');
        }
    }
}
