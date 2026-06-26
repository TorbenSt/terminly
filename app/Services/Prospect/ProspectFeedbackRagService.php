<?php

namespace App\Services\Prospect;

use App\Models\Company;
use App\Models\ProspectFeedback;
use App\Models\ProspectSearchProfile;
use Illuminate\Support\Facades\Log;

class ProspectFeedbackRagService
{
    public function __construct(
        protected GrokCollectionsService $collections,
    ) {}

    public function isEnabled(): bool
    {
        return $this->collections->isConfigured();
    }

    public function syncFeedback(ProspectFeedback $feedback): ?string
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $feedback->loadMissing(['prospect', 'company']);

        $company = $feedback->company;

        if (! $company) {
            return null;
        }

        try {
            $collectionId = $this->ensureCompanyCollection($company);
            $document = $this->buildDocument($feedback);
            $filename = "prospect-feedback-{$feedback->id}.json";

            $documentId = $this->collections->uploadDocument(
                $collectionId,
                $filename,
                json_encode($document, JSON_THROW_ON_ERROR),
                [
                    'company_id' => (string) $company->id,
                    'action' => $feedback->action->value,
                ],
            );

            $feedback->update(['grok_document_id' => $documentId]);

            return $documentId;
        } catch (\Throwable $e) {
            Log::warning('Prospect feedback collection upload failed', [
                'feedback_id' => $feedback->id,
                'company_id' => $feedback->company_id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return list<array{action: string, reason: string|null, context: string|null}>
     */
    public function relevantFeedback(Company $company, ProspectSearchProfile $profile): array
    {
        if (! $this->isEnabled()) {
            return $this->recentFeedbackFallback($company);
        }

        $collectionId = $company->grok_feedback_collection_id;

        if (! is_string($collectionId) || $collectionId === '') {
            return $this->recentFeedbackFallback($company);
        }

        $query = collect($profile->industries)
            ->push($profile->ai_instructions)
            ->filter()
            ->implode(' ');

        if ($query === '') {
            return $this->recentFeedbackFallback($company);
        }

        try {
            $matches = $this->collections->search($collectionId, $query);
        } catch (\Throwable $e) {
            if ($e->getMessage() === 'grok_collection_not_found') {
                $company->update(['grok_feedback_collection_id' => null]);

                try {
                    $collectionId = $this->ensureCompanyCollection($company);
                    $matches = $this->collections->search($collectionId, $query, retryOnNotFound: false);
                } catch (\Throwable $retryError) {
                    Log::warning('Prospect feedback RAG search failed after recreate', [
                        'company_id' => $company->id,
                        'error' => $retryError->getMessage(),
                    ]);

                    return $this->recentFeedbackFallback($company);
                }
            } else {
                Log::warning('Prospect feedback RAG search failed', [
                    'company_id' => $company->id,
                    'error' => $e->getMessage(),
                ]);

                return $this->recentFeedbackFallback($company);
            }
        }

        return collect($matches)
            ->map(fn (array $match) => $this->parseMatch($match))
            ->filter()
            ->values()
            ->all();
    }

    public function ensureCompanyCollection(Company $company): string
    {
        $name = $this->collectionName($company);
        $storedId = $company->grok_feedback_collection_id;

        $collectionId = $this->collections->ensureCollection($name, $storedId);

        if ($company->grok_feedback_collection_id !== $collectionId) {
            $company->update(['grok_feedback_collection_id' => $collectionId]);
        }

        return $collectionId;
    }

    private function collectionName(Company $company): string
    {
        $prefix = config('grok.collections.name_prefix', 'appointment-prospect-feedback');

        return "{$prefix}-{$company->id}";
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDocument(ProspectFeedback $feedback): array
    {
        $prospect = $feedback->prospect;

        return [
            'action' => $feedback->action->value,
            'reason' => $feedback->reason,
            'industry' => $prospect?->industry,
            'company_name' => $prospect?->company_name,
            'match_score' => $prospect?->match_score,
            'postal_code' => $prospect?->postal_code,
            'city' => $prospect?->city,
            'recorded_at' => $feedback->created_at?->toIso8601String(),
            'context' => $this->buildContextLine($feedback),
        ];
    }

    private function buildContextLine(ProspectFeedback $feedback): string
    {
        $prospect = $feedback->prospect;
        $parts = array_filter([
            $feedback->action->value,
            $prospect?->industry,
            $prospect?->company_name,
            $feedback->reason,
        ]);

        return implode(' — ', $parts);
    }

    /**
     * @param  array<string, mixed>  $match
     * @return array{action: string, reason: string|null, context: string|null}|null
     */
    private function parseMatch(array $match): ?array
    {
        $raw = $match['content'] ?? $match['text'] ?? $match['document'] ?? null;

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [
                'action' => 'context',
                'reason' => null,
                'context' => mb_substr($raw, 0, 500),
            ];
        }

        if (! is_array($decoded)) {
            return null;
        }

        return [
            'action' => (string) ($decoded['action'] ?? 'context'),
            'reason' => isset($decoded['reason']) ? (string) $decoded['reason'] : null,
            'context' => isset($decoded['context']) ? (string) $decoded['context'] : null,
        ];
    }

    /**
     * @return list<array{action: string, reason: string|null, context: string|null}>
     */
    private function recentFeedbackFallback(Company $company): array
    {
        return ProspectFeedback::query()
            ->where('company_id', $company->id)
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (ProspectFeedback $f) => [
                'action' => $f->action->value,
                'reason' => $f->reason,
                'context' => null,
            ])
            ->all();
    }
}
