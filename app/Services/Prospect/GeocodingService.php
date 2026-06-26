<?php

namespace App\Services\Prospect;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    /**
     * @return array{lat: float, lng: float, city: string|null}|null
     */
    public function geocodePostalCode(string $postalCode, string $country = 'DE'): ?array
    {
        $cacheKey = "geocode:{$country}:{$postalCode}";

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($postalCode, $country) {
            $apiKey = config('prospect_search.api_key');

            if (! $apiKey) {
                Log::warning('Google Places API key missing for geocoding.');

                return null;
            }

            $response = Http::timeout(15)->get(config('prospect_search.geocoding_url'), [
                'address' => "{$postalCode}, {$country}",
                'key' => $apiKey,
            ]);

            if (! $response->successful()) {
                return null;
            }

            $result = $response->json('results.0');

            if (! $result) {
                return null;
            }

            $location = $result['geometry']['location'] ?? null;

            if (! $location) {
                return null;
            }

            $city = collect($result['address_components'] ?? [])
                ->first(fn ($c) => in_array('locality', $c['types'] ?? [], true)
                    || in_array('postal_town', $c['types'] ?? [], true));

            return [
                'lat' => (float) $location['lat'],
                'lng' => (float) $location['lng'],
                'city' => $city['long_name'] ?? null,
            ];
        });
    }
}
