<?php

namespace App\Services\Prospect;

use App\Contracts\ProspectSource;
use App\Services\Apify\ApifyClientService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ApifyProspectSource implements ProspectSource
{
    public function __construct(
        protected ApifyClientService $apify,
        protected GeocodingService $geocoding,
    ) {}

    public function isConfigured(): bool
    {
        return $this->apify->isConfigured();
    }

    public function search(string $postalCode, int $radiusKm, array $industries, int $maxResults): Collection
    {
        if (! $this->isConfigured()) {
            Log::warning('Apify token missing — returning empty prospect list.');

            return collect();
        }

        $maxResults = min($maxResults, config('prospect_search.max_results_cap', 100));
        $center = $this->geocoding->geocodePostalCode($postalCode);
        $locationQuery = $center['city'] ?? null
            ? "{$postalCode} {$center['city']}, Deutschland"
            : "{$postalCode}, Deutschland";

        $searchStrings = collect($industries)
            ->map(fn ($industry) => trim((string) $industry))
            ->filter()
            ->values()
            ->all();

        if ($searchStrings === []) {
            return collect();
        }

        $actorId = (string) config('prospect_search.apify.actor_id', 'compass/crawler-google-places');

        $items = $this->apify->runActor($actorId, [
            'searchStringsArray' => $searchStrings,
            'locationQuery' => $locationQuery,
            'maxCrawledPlacesPerSearch' => $maxResults,
            'language' => 'de',
            'countryCode' => 'de',
            'maxImages' => 0,
            'maxReviews' => 0,
            'includeWebResults' => false,
            'scrapeContacts' => false,
        ]);

        $normalized = $items
            ->map(fn (array $place) => $this->normalizePlace($place))
            ->filter();

        Log::info('Apify prospect search finished', [
            'raw_count' => $items->count(),
            'normalized_count' => $normalized->count(),
            'location_query' => $locationQuery,
        ]);

        return $normalized
            ->unique('google_place_id')
            ->values()
            ->take($maxResults);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function normalizePlace(array $place): ?array
    {
        $placeId = $place['placeId'] ?? $place['place_id'] ?? null;
        $name = $place['title'] ?? $place['name'] ?? null;

        if (! $placeId || ! $name) {
            return null;
        }

        $postalCode = $place['postalCode'] ?? $place['postal_code'] ?? null;
        $city = $place['city'] ?? $place['state'] ?? null;

        if ((! $postalCode || ! $city) && ! empty($place['address'])) {
            [$parsedPostal, $parsedCity] = $this->parseGermanAddress((string) $place['address']);
            $postalCode ??= $parsedPostal;
            $city ??= $parsedCity;
        }

        $lat = $place['location']['lat'] ?? $place['location']['latitude'] ?? null;
        $lng = $place['location']['lng'] ?? $place['location']['longitude'] ?? null;

        return [
            'google_place_id' => (string) $placeId,
            'company_name' => (string) $name,
            'address' => $place['address'] ?? $place['street'] ?? null,
            'postal_code' => $postalCode,
            'city' => $city,
            'phone' => $place['phoneUnformatted'] ?? $place['phone'] ?? null,
            'email' => $place['email'] ?? null,
            'latitude' => $lat !== null ? (float) $lat : null,
            'longitude' => $lng !== null ? (float) $lng : null,
            'industry' => $place['categoryName'] ?? $place['category'] ?? ($place['categories'][0] ?? null),
            'source_url' => $place['url'] ?? $place['googleMapsUrl'] ?? null,
            'source' => 'apify_google_maps',
        ];
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    protected function parseGermanAddress(string $address): array
    {
        if (preg_match('/(\d{5})\s+([^,]+)/u', $address, $matches)) {
            return [$matches[1], trim($matches[2])];
        }

        return [null, null];
    }
}
