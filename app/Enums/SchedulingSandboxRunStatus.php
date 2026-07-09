<?php

namespace App\Enums;

enum SchedulingSandboxRunStatus: string
{
    case Ready = 'ready';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
