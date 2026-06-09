<?php

namespace App\Console\Commands;

use App\Jobs\ProcessSchedulingJob;
use App\Models\Company;
use App\Models\RecurringService;
use Illuminate\Console\Command;

class ScheduleDueAppointmentsCommand extends Command
{
    protected $signature = 'appointments:schedule-due {--company= : Company ID to schedule}';

    protected $description = 'Find due recurring services and trigger AI scheduling per company';

    public function handle(): int
    {
        $companyQuery = Company::query()->where('is_active', true);

        if ($companyId = $this->option('company')) {
            $companyQuery->where('id', $companyId);
        }

        $companies = $companyQuery->get();
        $scheduled = 0;

        foreach ($companies as $company) {
            $dueCount = RecurringService::where('company_id', $company->id)
                ->where('is_active', true)
                ->where('next_due_at', '<=', now())
                ->count();

            if ($dueCount === 0) {
                $this->line("Company #{$company->id} ({$company->name}): nothing due");

                continue;
            }

            ProcessSchedulingJob::dispatch($company->id);
            $scheduled++;
            $this->info("Company #{$company->id} ({$company->name}): queued {$dueCount} due service(s)");
        }

        $this->info("Queued scheduling for {$scheduled} company/companies.");

        return self::SUCCESS;
    }
}
