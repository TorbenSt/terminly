<?php

namespace App\Services\Prospect;

use App\Models\BillingSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * xAI Grok Collections (Management API + documents/search).
 * Pattern from tiktoktrend — per logical collection name with persisted ID.
 */
class GrokCollectionsService
{
    private const STATS_CACHE_KEY = 'grok.prospect_collection_stats';

    public function ensureCollection(string $collectionName, ?string &$storedCollectionId = null): string
    {
        if (is_string($storedCollectionId) && $storedCollectionId !== '' && $this->isCollectionRegistered($storedCollectionId)) {
            return $storedCollectionId;
        }

        if (is_string($storedCollectionId) && $storedCollectionId !== '') {
            Log::warning('Stored Grok collection id is missing remotely; recreating', [
                'collection_id' => $storedCollectionId,
                'collection_name' => $collectionName,
            ]);
            $storedCollectionId = null;
        }

        $found = $this->findCollectionIdByName($collectionName);

        if ($found !== null) {
            $storedCollectionId = $found;

            return $found;
        }

        $created = $this->createCollection($collectionName);
        $storedCollectionId = $created;

        return $created;
    }

    public function createCollection(string $collectionName): string
    {
        $response = $this->collectionsClient()
            ->post('/collections', [
                'collection_name' => $collectionName,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                "Failed to create Grok collection [{$collectionName}]: ".$response->body()
            );
        }

        $collectionId = $response->json('collection_id')
            ?? $response->json('id')
            ?? $response->json('collection.id');

        if (! is_string($collectionId) || $collectionId === '') {
            throw new RuntimeException('Grok collection created but no collection_id returned.');
        }

        Log::info('Grok collection created', ['collection_id' => $collectionId, 'name' => $collectionName]);

        return $collectionId;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function uploadDocument(string $collectionId, string $filename, string $jsonContent, array $metadata = []): string
    {
        $multipart = [
            ['name' => 'name', 'contents' => $filename],
            ['name' => 'content_type', 'contents' => 'application/json'],
            ['name' => 'data', 'contents' => $jsonContent, 'filename' => $filename],
        ];

        if ($metadata !== []) {
            $multipart[] = ['name' => 'fields', 'contents' => json_encode($metadata)];
        }

        $response = $this->collectionsClient()
            ->asMultipart()
            ->post("/collections/{$collectionId}/documents", $multipart);

        if ($response->successful()) {
            $fileId = $response->json('file_id')
                ?? $response->json('document_id')
                ?? $response->json('id');

            if (is_string($fileId) && $fileId !== '') {
                $this->incrementUploadStats();

                return $fileId;
            }
        }

        return $this->uploadDocumentTwoStep($collectionId, $filename, $jsonContent);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $collectionId, string $query, ?int $limit = null, bool $retryOnNotFound = true): array
    {
        $limit ??= (int) config('grok.collections.search_limit', 10);

        $response = $this->apiClient()
            ->post('/documents/search', [
                'query' => $query,
                'source' => [
                    'collection_ids' => [$collectionId],
                ],
                'retrieval_mode' => ['type' => 'hybrid'],
                'limit' => $limit,
            ]);

        if (! $response->successful()) {
            if ($retryOnNotFound && $this->isCollectionNotFoundResponse($response)) {
                Log::warning('Grok collection search referenced missing collection', [
                    'collection_id' => $collectionId,
                ]);

                throw new RuntimeException('grok_collection_not_found');
            }

            throw new RuntimeException('Grok collection search failed: '.$response->body());
        }

        $matches = $response->json('matches')
            ?? $response->json('results')
            ?? $response->json('documents')
            ?? [];

        $this->incrementSearchStats();

        return is_array($matches) ? array_values($matches) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getUsageStats(): array
    {
        return Cache::remember(self::STATS_CACHE_KEY, 300, fn () => [
            'uploads_total' => (int) BillingSetting::get('grok_prospect_uploads_total', '0'),
            'searches_total' => (int) BillingSetting::get('grok_prospect_searches_total', '0'),
            'last_upload_at' => BillingSetting::get('grok_prospect_last_upload_at'),
            'last_search_at' => BillingSetting::get('grok_prospect_last_search_at'),
        ]);
    }

    public function isConfigured(): bool
    {
        return $this->xaiApiKey() !== '' && $this->xaiManagementApiKey() !== '';
    }

    private function uploadDocumentTwoStep(string $collectionId, string $filename, string $jsonContent): string
    {
        $uploadResponse = $this->apiClient()
            ->attach('file', $jsonContent, $filename)
            ->post('/files', [
                'purpose' => 'assistants',
            ]);

        if (! $uploadResponse->successful()) {
            throw new RuntimeException('Grok file upload failed: '.$uploadResponse->body());
        }

        $fileId = $uploadResponse->json('id');

        if (! is_string($fileId) || $fileId === '') {
            throw new RuntimeException('Grok file upload returned no file id.');
        }

        $addResponse = $this->collectionsClient()
            ->withBody('', 'application/octet-stream')
            ->post("/collections/{$collectionId}/documents/{$fileId}");

        if (! $addResponse->successful()) {
            throw new RuntimeException('Failed to add file to Grok collection: '.$addResponse->body());
        }

        $this->incrementUploadStats();

        return $fileId;
    }

    private function findCollectionIdByName(string $collectionName): ?string
    {
        $response = $this->collectionsClient()->get('/collections');

        if (! $response->successful()) {
            return null;
        }

        $collections = $response->json('collections') ?? $response->json('data') ?? [];

        foreach ($collections as $collection) {
            $name = $collection['collection_name'] ?? $collection['name'] ?? null;

            if ($name === $collectionName) {
                $id = $collection['collection_id'] ?? $collection['id'] ?? null;

                if (is_string($id) && $id !== '') {
                    return $id;
                }
            }
        }

        return null;
    }

    private function isCollectionRegistered(string $collectionId): bool
    {
        $response = $this->collectionsClient()->get('/collections');

        if (! $response->successful()) {
            Log::warning('Could not verify Grok collection registration; using stored id', [
                'collection_id' => $collectionId,
                'status' => $response->status(),
            ]);

            return true;
        }

        $collections = $response->json('collections') ?? $response->json('data') ?? [];

        foreach ($collections as $collection) {
            $id = $collection['collection_id'] ?? $collection['id'] ?? null;

            if ($id === $collectionId) {
                return true;
            }
        }

        return false;
    }

    private function isCollectionNotFoundResponse(Response $response): bool
    {
        if ($response->status() === 404) {
            return true;
        }

        $code = $response->json('code');

        return is_string($code) && $code === 'not-found';
    }

    private function incrementUploadStats(): void
    {
        $total = (int) BillingSetting::get('grok_prospect_uploads_total', '0');
        BillingSetting::set('grok_prospect_uploads_total', (string) ($total + 1));
        BillingSetting::set('grok_prospect_last_upload_at', now()->toIso8601String());
        Cache::forget(self::STATS_CACHE_KEY);
    }

    private function incrementSearchStats(): void
    {
        $total = (int) BillingSetting::get('grok_prospect_searches_total', '0');
        BillingSetting::set('grok_prospect_searches_total', (string) ($total + 1));
        BillingSetting::set('grok_prospect_last_search_at', now()->toIso8601String());
        Cache::forget(self::STATS_CACHE_KEY);
    }

    private function collectionsClient(): PendingRequest
    {
        $key = $this->xaiManagementApiKey();

        if ($key === '') {
            throw new RuntimeException('XAI Management API Key is not configured (Collections permission).');
        }

        return Http::baseUrl((string) config('grok.xai.collections_base_url'))
            ->withToken($key)
            ->acceptJson()
            ->timeout(120);
    }

    private function apiClient(): PendingRequest
    {
        $key = $this->xaiApiKey();

        if ($key === '') {
            throw new RuntimeException('XAI API Key is not configured.');
        }

        return Http::baseUrl((string) config('grok.xai.api_base_url'))
            ->withToken($key)
            ->acceptJson()
            ->timeout(120);
    }

    private function xaiApiKey(): string
    {
        return (string) (config('grok.xai.api_key') ?? '');
    }

    private function xaiManagementApiKey(): string
    {
        return (string) (config('grok.xai.management_api_key') ?? '');
    }
}
