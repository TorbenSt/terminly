<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\SchedulingSandboxRun;
use Illuminate\Console\Command;

class PurgeSchedulingSandboxCommand extends Command
{
    protected $signature = 'scheduling-lab:purge';

    protected $description = 'Delete scheduling sandbox companies and runs older than the configured retention period';

    public function handle(): int
    {
        $days = config('scheduling_lab.purge_after_days', 7);
        $cutoff = now()->subDays($days);

        $runs = SchedulingSandboxRun::query()
            ->where('created_at', '<', $cutoff)
            ->with('company')
            ->get();

        $count = 0;

        foreach ($runs as $run) {
            $run->company?->delete();
            $run->delete();
            $count++;
        }

        $orphans = Company::query()
            ->where('is_sandbox', true)
            ->where('created_at', '<', $cutoff)
            ->whereDoesntHave('users')
            ->get();

        foreach ($orphans as $company) {
            $company->delete();
            $count++;
        }

        $this->info("Purged {$count} sandbox run(s)/compan(ies) older than {$days} days.");

        return self::SUCCESS;
    }
}
