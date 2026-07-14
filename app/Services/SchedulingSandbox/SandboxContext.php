<?php

namespace App\Services\SchedulingSandbox;

use App\Models\Company;
use App\Models\SchedulingSandboxRun;

class SandboxContext
{
    private static ?SchedulingSandboxRun $run = null;

    public static function set(?SchedulingSandboxRun $run): void
    {
        self::$run = $run;
    }

    public static function run(): ?SchedulingSandboxRun
    {
        return self::$run;
    }

    public static function shouldForceFallback(?Company $company = null): bool
    {
        $run = self::$run ?? self::latestRunForCompany($company);

        return $run !== null && ! $run->use_grok_live;
    }

    private static function latestRunForCompany(?Company $company): ?SchedulingSandboxRun
    {
        if ($company === null || ! $company->isSandbox()) {
            return null;
        }

        return SchedulingSandboxRun::query()
            ->where('company_id', $company->id)
            ->latest()
            ->first();
    }
}
