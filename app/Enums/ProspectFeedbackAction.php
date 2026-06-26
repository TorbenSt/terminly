<?php

namespace App\Enums;

enum ProspectFeedbackAction: string
{
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Converted = 'converted';
    case EmailSent = 'email_sent';
    case EmailReplied = 'email_replied';
}
