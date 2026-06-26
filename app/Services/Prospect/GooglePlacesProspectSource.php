<?php

namespace App\Services\Prospect;

use App\Contracts\ProspectSource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GooglePlacesProspectSource implements ProspectSource
{
    public function __construct(protected GeocodingService $geocoding) {}

    public function isConfigured(): bool
    {
        return filled(config('prospect_search.api_key'));
    }

    /**
     * @return Collection<int, array{
     *     google_place_id: string,
     *     company_name: string,
     *     address: string|null,
     *     postal_code: string|null,
     *     city: string|null,
     *     phone: string|null,
     *     email: null,
     *     latitude: float|null,
     *     longitude: float|null,
     *     industry: string|null,
     *     source_url: string|null
     * }>
     */
    public function search(string $postalCode, int $radiusKm, array $industries, int $maxResults): Collection
    {
        $apiKey = config('prospect_search.api_key');

        if (! $apiKey) {
            Log::warning('Google Places API key missing — returning empty prospect list.');

            return collect();
        }

        $center = $this->geocoding->geocodePostalCode($postalCode);

        if (! $center) {
            return collect();
        }

        $query = $this->buildTextQuery($industries, $postalCode, $center['city']);
        $maxResults = min($maxResults, config('prospect_search.max_results_cap', 100));

        $response = Http::timeout(30)
            ->withHeaders([
                'X-Goog-Api-Key' => $apiKey,
                'X-Goog-FieldMask' => config('prospect_search.field_mask'),
                'Content-Type' => 'application/json',
            ])
            ->post(config('prospect_search.places_search_url'), [
                'textQuery' => $query,
                'maxResultCount' => $maxResults,
                'languageCode' => 'de',
                'locationBias' => [
                    'circle' => [
                        'center' => [
                            'latitude' => $center['lat'],
                            'longitude' => $center['lng'],
                        ],
                        'radius' => (float) ($radiusKm * 1000),
                    ],
                ],
            ]);

        if (! $response->successful()) {
            Log::error('Google Places search failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return collect();
        }

        return collect($response->json('places', []))
            ->map(fn (array $place) => $this->normalizePlace($place))
            ->filter()
            ->values();
    }

    protected function buildTextQuery(array $industries, string $postalCode, ?string $city): string
    {
        $industryPart = implode(' OR ', array_map('trim', $industries));
        $locationPart = trim("{$postalCode} ".($city ?? 'Deutschland'));

        return "{$industryPart} in {$locationPart}";
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function normalizePlace(array $place): ?array
    {
        $placeId = $place['id'] ?? null;
        $name = $place['displayName']['text'] ?? null;

        if (! $placeId || ! $name) {
            return null;
        }

        $components = collect($place['addressComponents'] ?? []);
        $postalCode = $components->first(fn ($c) => in_array('postal_code', $c['types'] ?? [], true))['longText'] ?? null;
        $city = $components->first(fn ($c) => in_array('locality', $c['types'] ?? [], true)
            || in_array('postal_town', $c['types'] ?? [], true))['longText'] ?? null;

        return [
            'google_place_id' => $placeId,
            'company_name' => $name,
            'address' => $place['formattedAddress'] ?? null,
            'postal_code' => $postalCode,
            'city' => $city,
            'phone' => $place['nationalPhoneNumber'] ?? null,
            'email' => null,
            'latitude' => $place['location']['latitude'] ?? null,
            'longitude' => $place['location']['longitude'] ?? null,
            'industry' => $place['primaryType'] ?? ($place['types'][0] ?? null),
            'source_url' => $place['googleMapsUri'] ?? null,
            'source' => 'google_places',
        ];
    }
}
