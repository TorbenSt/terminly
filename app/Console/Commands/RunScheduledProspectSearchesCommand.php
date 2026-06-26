<?php

namespace App\Console\Commands;

use App\Enums\ProspectDataSource;
use App\Enums\ProspectSearchTrigger;
use App\Models\ProspectSearchProfile;
use App\Models\ProspectSearchRun;
use App\Services\Prospect\ProspectSearchDispatcher;
use Illuminate\Console\Command;

class RunScheduledProspectSearchesCommand extends Command
{
    protected $signature = 'prospects:run-scheduled';

    protected $description = 'Startet fällige geplante Kundensuchen';

    public function handle(): int
    {
        $profiles = ProspectSearchProfile::query()
            ->where('is_active', true)
            ->where('schedule_enabled', true)
            ->where(function ($query) {
                $query->whereNull('next_run_at')->orWhere('next_run_at', '<=', now());
            })
            ->with('company')
            ->get();

        foreach ($profiles as $profile) {
            $company = $profile->company;

            if (! $company || ! $company->hasProspectSearchAccess()) {
                continue;
            }

            $run = ProspectSearchRun::create([
                'company_id' => $company->id,
                'prospect_search_profile_id' => $profile->id,
                'trigger' => ProspectSearchTrigger::Scheduled,
                'data_source' => ($profile->data_source ?? ProspectDataSource::GooglePlaces)->value,
                'requested_max_results' => $profile->effectiveMaxResults($company->effectivePlan()),
            ]);

            ProspectSearchDispatcher::dispatch($run->id);

            $profile->update([
                'last_run_at' => now(),
                'next_run_at' => now()->addWeek(),
            ]);
        }

        return self::SUCCESS;
    }
}
