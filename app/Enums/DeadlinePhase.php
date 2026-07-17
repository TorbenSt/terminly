<?php

namespace App\Enums;

enum DeadlinePhase: string
{
    case Green = 'green';
    case Yellow = 'yellow';
    case Red = 'red';

    public function label(): string
    {
        return match ($this) {
            self::Green => 'Grün',
            self::Yellow => 'Gelb',
            self::Red => 'Rot',
        };
    }
}
