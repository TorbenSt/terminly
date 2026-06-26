<?php

namespace Tests\Feature;

use App\Jobs\SearchCustomerProspectsJob;
use App\Services\Prospect\ProspectSearchDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ProspectSearchDispatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_dispatch_queues_search_job(): void
    {
        Bus::fake();

        ProspectSearchDispatcher::dispatch(42, manual: true);

        Bus::assertDispatched(SearchCustomerProspectsJob::class, fn (SearchCustomerProspectsJob $job) => $job->runId === 42);
    }

    public function test_scheduled_dispatch_queues_search_job(): void
    {
        Bus::fake();

        ProspectSearchDispatcher::dispatch(7, manual: false);

        Bus::assertDispatched(SearchCustomerProspectsJob::class, fn (SearchCustomerProspectsJob $job) => $job->runId === 7);
    }
}
