<?php

namespace App\Enums;

enum SchedulingSandboxScenario: string
{
    case SimpleMaintenance = 'simple_maintenance';
    case RegionalTwoStaff = 'regional_two_staff';
    case RegionalTour = 'regional_tour';
    case StaffQualification = 'staff_qualification';
    case GrokFallback = 'grok_fallback';
    case RealLifeCapacity = 'real_life_capacity';

    public function label(): string
    {
        return match ($this) {
            self::SimpleMaintenance => 'Einfach: eine fällige Wartung',
            self::RegionalTwoStaff => 'Region: zwei Techniker, zwei PLZ-Cluster',
            self::RegionalTour => 'Regionale Tour',
            self::StaffQualification => 'Nur qualifizierter Mitarbeiter',
            self::GrokFallback => 'Einfach (nur Fallback, kein Grok)',
            self::RealLifeCapacity => 'Real Life: volle Kalender',
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
            self::RealLifeCapacity => '5 Mitarbeiter (nur 2 qualifiziert), 6+ PLZ-Cluster, Kalender ~65 % voll über 4 Monate – Stress-Test für reale Terminfindung.',
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
