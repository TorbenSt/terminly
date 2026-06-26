<?php

namespace App\Enums;

enum ProspectSearchTrigger: string
{
    case Manual = 'manual';
    case Scheduled = 'scheduled';
}
