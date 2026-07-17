<?php

namespace App\Enums;

enum StaffCustomerBinding: string
{
    case Off = 'off';
    case Prefer = 'prefer';
    case StrictWithExceptions = 'strict_with_exceptions';
    case Hard = 'hard';

    public function label(): string
    {
        return match ($this) {
            self::Off => 'Aus (keine Bindung)',
            self::Prefer => 'Bevorzugt (weich)',
            self::StrictWithExceptions => 'Strikt mit Ausnahmen',
            self::Hard => 'Hart gebunden',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Off => 'Nur Qualifikation, Region und Lastverteilung.',
            self::Prefer => 'Stammansprechpartner zuerst; bei Konflikten normaler Pool.',
            self::StrictWithExceptions => 'Stamm muss, außer bei kritischer Frist oder Unmöglichkeit.',
            self::Hard => 'Nur Stamm oder Vertretung — sonst keine Auto-Zuweisung.',
        };
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $mode) => [
            'value' => $mode->value,
            'label' => $mode->label(),
            'description' => $mode->description(),
        ], self::cases());
    }
}
