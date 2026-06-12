<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\Billing\UsageSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncCompanyUsageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $companyId) {}

    public function handle(UsageSyncService $usageSync): void
    {
        $company = Company::find($this->companyId);

        if ($company) {
            $usageSync->sync($company);
        }
    }
}
