<?php

return [
    'api_key' => env('GOOGLE_PLACES_API_KEY'),

    'geocoding_url' => env('GOOGLE_GEOCODING_URL', 'https://maps.googleapis.com/maps/api/geocode/json'),

    'places_search_url' => env('GOOGLE_PLACES_SEARCH_URL', 'https://places.googleapis.com/v1/places:searchText'),

    'default_radius_km' => (int) env('PROSPECT_DEFAULT_RADIUS_KM', 10),

    'max_results_cap' => (int) env('PROSPECT_MAX_RESULTS_CAP', 100),

    'retention_days' => (int) env('PROSPECT_RETENTION_DAYS', 365),

    'outreach_rate_limit_per_day' => (int) env('PROSPECT_OUTREACH_RATE_LIMIT_PER_DAY', 20),

    'dispatch_after_response' => (bool) env('PROSPECT_DISPATCH_AFTER_RESPONSE', true),

    'apify' => [
        'token' => env('APIFY_TOKEN'),
        'actor_id' => env('APIFY_PROSPECT_ACTOR_ID', 'compass/crawler-google-places'),
        'poll_max_attempts' => (int) env('APIFY_POLL_MAX_ATTEMPTS', 72),
        'poll_interval_seconds' => (int) env('APIFY_POLL_INTERVAL_SECONDS', 5),
    ],

    'field_mask' => 'places.id,places.displayName,places.formattedAddress,places.addressComponents,places.nationalPhoneNumber,places.websiteUri,places.googleMapsUri,places.location,places.primaryType,places.types',
];
