<?php

namespace App\Enums;

enum SchedulingSandboxMessageType: string
{
    case Proposal = 'proposal';
    case Negotiation = 'negotiation';
    case Escalation = 'escalation';
}
