<?php

namespace App\Enums;

enum NegotiationStatus: string
{
    case Pending = 'pending';
    case Processed = 'processed';
    case Escalated = 'escalated';
}
