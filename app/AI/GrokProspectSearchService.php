<?php

namespace App\AI;

use App\AI\Prompts\ProspectSearchSystemPrompt;
use App\Models\Company;
use App\Models\ProspectSearchProfile;
use App\Services\Prospect\ProspectFeedbackRagService;
use GrokPHP\Client\Config\ChatOptions;
use GrokPHP\Client\Exceptions\GrokException;
use GrokPHP\Laravel\Facades\GrokAI;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class GrokProspectSearchService
{
    public function __construct(
        protected ProspectFeedbackRagService $feedbackRag,
    ) {}

    /**
     * @param  Collection<int, array<string, mixed>>  $candidates
     * @return Collection<int, array{google_place_id: string, match_score: int, match_reason: string, discard: bool}>
     */
    public function scoreCandidates(Company $company, ProspectSearchProfile $profile, Collection $candidates): Collection
    {
        if ($candidates->isEmpty()) {
            return collect();
        }

        if (empty(config('grok.api_key'))) {
            return $this->fallbackScore($candidates, $profile);
        }

        $payload = [
            'industries' => $profile->industries,
            'ai_instructions' => $profile->ai_instructions,
            'feedback' => $this->feedbackRag->relevantFeedback($company, $profile),
            'candidates' => $candidates->map(fn ($c) => [
                'google_place_id' => $c['google_place_id'],
                'company_name' => $c['company_name'],
                'industry' => $c['industry'],
                'postal_code' => $c['postal_code'],
                'city' => $c['city'],
            ])->values()->all(),
        ];

        try {
            $response = GrokAI::chat(
                [
                    ['role' => 'system', 'content' => ProspectSearchSystemPrompt::build()],
                    ['role' => 'user', 'content' => json_encode($payload, JSON_THROW_ON_ERROR)],
                ],
                new ChatOptions(temperature: (float) config('grok.default_temperature', 0.3))
            );

            $parsed = json_decode($response->content(), true, 512, JSON_THROW_ON_ERROR);

            return collect($parsed['results'] ?? [])->map(fn ($row) => [
                'google_place_id' => (string) $row['google_place_id'],
                'match_score' => (int) ($row['match_score'] ?? 0),
                'match_reason' => (string) ($row['match_reason'] ?? ''),
                'discard' => (bool) ($row['discard'] ?? false),
            ]);
        } catch (GrokException|\JsonException $e) {
            Log::warning('Grok prospect scoring failed, using fallback.', ['error' => $e->getMessage()]);

            return $this->fallbackScore($candidates, $profile);
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $candidates
     */
    protected function fallbackScore(Collection $candidates, ProspectSearchProfile $profile): Collection
    {
        $keywords = collect($profile->industries)->map(fn ($i) => mb_strtolower($i));

        return $candidates->map(function (array $candidate) use ($keywords) {
            $haystack = mb_strtolower(($candidate['company_name'] ?? '').' '.($candidate['industry'] ?? ''));
            $score = $keywords->contains(fn ($kw) => str_contains($haystack, $kw)) ? 75 : 40;

            return [
                'google_place_id' => $candidate['google_place_id'],
                'match_score' => $score,
                'match_reason' => 'Automatische Bewertung (ohne KI)',
                'discard' => $score < 50,
            ];
        });
    }
}
