<?php

namespace App\Enums;

enum SchedulingSandboxMode: string
{
    case Scenario = 'scenario';
    case CompanySnapshot = 'company_snapshot';
}
