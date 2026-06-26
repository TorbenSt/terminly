<?php

namespace App\Services\Prospect;

use App\Models\Company;
use App\Models\ProspectOutreachEmail;

class ProspectOutreachLimitService
{
    public function dailyLimit(Company $company): int
    {
        $planLimit = $company->effectivePlan()?->prospect_outreach_limit_per_day;

        if ($planLimit !== null) {
            return max(0, $planLimit);
        }

        return max(0, (int) config('prospect_search.outreach_rate_limit_per_day', 20));
    }

    public function sentToday(Company $company): int
    {
        return ProspectOutreachEmail::query()
            ->where('company_id', $company->id)
            ->whereIn('status', ['sent', 'queued'])
            ->whereDate('created_at', today())
            ->count();
    }

    public function canSend(Company $company): bool
    {
        $limit = $this->dailyLimit($company);

        if ($limit === 0) {
            return false;
        }

        return $this->sentToday($company) < $limit;
    }

    public function remainingToday(Company $company): int
    {
        return max(0, $this->dailyLimit($company) - $this->sentToday($company));
    }
}
