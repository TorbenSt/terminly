<?php

namespace App\Console\Commands;

use App\Enums\ProspectStatus;
use App\Models\CustomerProspect;
use Illuminate\Console\Command;

class PurgeOldProspectsCommand extends Command
{
    protected $signature = 'prospects:purge-old {--days= : Aufbewahrungsdauer in Tagen (Standard: config)}';

    protected $description = 'Löscht verworfene/abgelehnte Prospects nach Ablauf der Aufbewahrungsfrist (DSGVO)';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('prospect_search.retention_days', 365));

        if ($days < 1) {
            $this->error('Aufbewahrungsdauer muss mindestens 1 Tag sein.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);

        $deleted = CustomerProspect::query()
            ->whereIn('status', [ProspectStatus::Rejected, ProspectStatus::Discarded, ProspectStatus::OptedOut])
            ->where('updated_at', '<', $cutoff)
            ->delete();

        $this->info("{$deleted} alte Prospects gelöscht (älter als {$days} Tage).");

        return self::SUCCESS;
    }
}
