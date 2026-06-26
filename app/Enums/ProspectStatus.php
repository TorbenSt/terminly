<?php

namespace App\Enums;

enum ProspectStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Replied = 'replied';
    case Interested = 'interested';
    case Rejected = 'rejected';
    case OptedOut = 'opted_out';
    case Converted = 'converted';
    case Discarded = 'discarded';
}
