<?php

namespace App\Enums;

enum SchedulingSandboxScenario: string
{
    case SimpleMaintenance = 'simple_maintenance';
    case RegionalTwoStaff = 'regional_two_staff';
    case RegionalTour = 'regional_tour';
    case StaffQualification = 'staff_qualification';
    case GrokFallback = 'grok_fallback';

    public function label(): string
    {
        return match ($this) {
            self::SimpleMaintenance => 'Einfach: eine fällige Wartung',
            self::RegionalTwoStaff => 'Region: zwei Techniker, zwei PLZ-Cluster',
            self::RegionalTour => 'Regionale Tour',
            self::StaffQualification => 'Nur qualifizierter Mitarbeiter',
            self::GrokFallback => 'Einfach (nur Fallback, kein Grok)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SimpleMaintenance => '1 Mitarbeiter, 1 fälliger Kunde, leerer Kalender.',
            self::RegionalTwoStaff => '2 Techniker in verschiedenen PLZ-Regionen, 4 fällige Kunden.',
            self::RegionalTour => 'Bestehende PLZ-Tour (Berlin/Hamburg), neuer Kunde in gleicher Region.',
            self::StaffQualification => '2 Mitarbeiter mit unterschiedlichen Qualifikationen.',
            self::GrokFallback => 'Wie „Einfach“, erzwingt den deterministischen Fallback-Scheduler.',
        };
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $scenario) => [
            'value' => $scenario->value,
            'label' => $scenario->label(),
            'description' => $scenario->description(),
        ], self::cases());
    }
}
