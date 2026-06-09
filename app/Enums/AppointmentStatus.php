<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Proposed = 'proposed';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Negotiation = 'negotiation';

    public function label(): string
    {
        return match ($this) {
            self::Proposed => 'Vorgeschlagen',
            self::Confirmed => 'Bestätigt',
            self::Completed => 'Abgeschlossen',
            self::Cancelled => 'Storniert',
            self::Negotiation => 'Verhandlung',
        };
    }
}
