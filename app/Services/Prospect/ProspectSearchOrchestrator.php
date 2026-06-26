<?php

namespace App\Services\Prospect;

use App\AI\GrokProspectSearchService;
use App\Enums\ProspectDataSource;
use App\Enums\ProspectSearchRunStatus;
use App\Enums\ProspectStatus;
use App\Models\Company;
use App\Models\CustomerProspect;
use App\Models\ProspectSearchProfile;
use App\Models\ProspectSearchRun;
use Illuminate\Support\Facades\Log;

class ProspectSearchOrchestrator
{
    public function __construct(
        protected ProspectSourceResolver $sources,
        protected ProspectDeduplicationService $deduplication,
        protected GrokProspectSearchService $grok,
    ) {}

    public function run(ProspectSearchRun $run): void
    {
        $run->update([
            'status' => ProspectSearchRunStatus::Running,
            'started_at' => now(),
        ]);

        try {
            $profile = $run->profile()->firstOrFail();
            $company = $run->company()->firstOrFail();

            if (! $company->hasProspectSearchAccess()) {
                throw new \RuntimeException('Kundensuche ist für diese Firma nicht freigeschaltet.');
            }

            $dataSource = $run->data_source
                ? ProspectDataSource::from($run->data_source)
                : ($profile->data_source ?? ProspectDataSource::GooglePlaces);
            $source = $this->sources->resolve($dataSource);

            if (! $source->isConfigured()) {
                throw new \RuntimeException("Datenquelle „{$dataSource->label()}“ ist nicht konfiguriert.");
            }

            $run->update(['data_source' => $dataSource->value]);

            $maxResults = min(
                $run->requested_max_results,
                $profile->effectiveMaxResults($company->effectivePlan())
            );

            $candidates = $source->search(
                $profile->postal_code,
                $profile->radius_km,
                $profile->industries,
                $maxResults * 2
            );

            $run->update(['candidates_found' => $candidates->count()]);

            $filtered = $candidates->filter(function (array $candidate) use ($company, $profile, $run) {
                if ($this->deduplication->isDuplicate($company, $candidate, $profile->exclude_existing_customers)) {
                    $run->increment('duplicates_skipped');

                    return false;
                }

                return true;
            })->values();

            $scores = $this->grok->scoreCandidates($company, $profile, $filtered)
                ->keyBy('google_place_id');

            $saved = 0;

            foreach ($filtered as $candidate) {
                if ($saved >= $maxResults) {
                    break;
                }

                $score = $scores->get($candidate['google_place_id']);

                if (! $score || ($score['discard'] ?? false)) {
                    continue;
                }

                CustomerProspect::updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'google_place_id' => $candidate['google_place_id'],
                    ],
                    [
                        'prospect_search_run_id' => $run->id,
                        'status' => ProspectStatus::New,
                        'company_name' => $candidate['company_name'],
                        'email' => $candidate['email'],
                        'phone' => $candidate['phone'],
                        'address' => $candidate['address'],
                        'postal_code' => $candidate['postal_code'],
                        'city' => $candidate['city'],
                        'latitude' => $candidate['latitude'],
                        'longitude' => $candidate['longitude'],
                        'industry' => $candidate['industry'],
                        'match_score' => $score['match_score'],
                        'match_reason' => $score['match_reason'],
                        'source' => $candidate['source'] ?? $dataSource->value,
                        'source_url' => $candidate['source_url'],
                        'discovered_at' => now(),
                    ]
                );

                $saved++;
            }

            $run->update([
                'status' => ProspectSearchRunStatus::Completed,
                'prospects_saved' => $saved,
                'finished_at' => now(),
            ]);

            $profile->update(['last_run_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('Prospect search run failed.', ['run_id' => $run->id, 'error' => $e->getMessage()]);

            $run->update([
                'status' => ProspectSearchRunStatus::Failed,
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);
        }
    }
}
