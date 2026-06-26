<?php

namespace App\Services\Apify;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Generic Apify actor runner (pattern from tiktoktrend).
 */
class ApifyClientService
{
    private const API_BASE = 'https://api.apify.com/v2';

    public function isConfigured(): bool
    {
        return (string) config('prospect_search.apify.token') !== '';
    }

    /**
     * @param  array<string, mixed>  $input
     * @return Collection<int, array<string, mixed>>
     */
    public function runActor(string $actorId, array $input): Collection
    {
        $token = (string) config('prospect_search.apify.token');

        if ($token === '') {
            throw new RuntimeException('APIFY_TOKEN is not configured.');
        }

        $actorPath = str_replace('/', '~', $actorId);
        $maxAttempts = (int) config('prospect_search.apify.poll_max_attempts', 72);
        $pollSeconds = (int) config('prospect_search.apify.poll_interval_seconds', 5);

        $startResponse = Http::timeout(30)
            ->post(self::API_BASE."/acts/{$actorPath}/runs?token={$token}", $input);

        if (! $startResponse->successful()) {
            throw new RuntimeException("Apify actor start failed: {$startResponse->body()}");
        }

        $runId = $startResponse->json('data.id');

        if (! is_string($runId)) {
            throw new RuntimeException('Apify returned no run id.');
        }

        Log::info('Apify actor started', ['actor' => $actorId, 'run_id' => $runId]);

        $datasetId = $this->waitForRun($runId, $token, $maxAttempts, $pollSeconds);

        return $this->fetchDatasetItems($datasetId, $token);
    }

    private function waitForRun(string $runId, string $token, int $maxAttempts, int $pollSeconds): string
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            sleep($pollSeconds);

            $response = Http::get(self::API_BASE."/actor-runs/{$runId}?token={$token}");

            if (! $response->successful()) {
                continue;
            }

            $status = $response->json('data.status');

            if ($status === 'SUCCEEDED') {
                $datasetId = $response->json('data.defaultDatasetId');

                if (! is_string($datasetId)) {
                    throw new RuntimeException('Apify run succeeded but no dataset id.');
                }

                return $datasetId;
            }

            if (in_array($status, ['FAILED', 'ABORTED', 'TIMED-OUT'], true)) {
                throw new RuntimeException("Apify run {$runId} ended with status: {$status}");
            }
        }

        throw new RuntimeException("Apify run {$runId} timed out waiting for completion.");
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function fetchDatasetItems(string $datasetId, string $token): Collection
    {
        $response = Http::get(self::API_BASE."/datasets/{$datasetId}/items?token={$token}&format=json");

        if (! $response->successful()) {
            throw new RuntimeException('Failed to fetch Apify dataset items.');
        }

        $items = $response->json();

        return collect(is_array($items) ? $items : []);
    }
}
