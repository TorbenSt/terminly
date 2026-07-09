<?php

namespace App\Services\SchedulingSandbox;

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

    public static function shouldForceFallback(): bool
    {
        $run = self::$run;

        return $run !== null && ! $run->use_grok_live;
    }
}
