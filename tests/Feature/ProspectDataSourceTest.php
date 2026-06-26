<?php

namespace Tests\Feature;

use App\Enums\ProspectDataSource;
use App\Models\Company;
use App\Models\ProspectSearchProfile;
use App\Services\Prospect\ApifyProspectSource;
use App\Services\Prospect\ProspectSourceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProspectDataSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolver_returns_both_sources(): void
    {
        $resolver = app(ProspectSourceResolver::class);

        $this->assertInstanceOf(\App\Services\Prospect\GooglePlacesProspectSource::class, $resolver->resolve(ProspectDataSource::GooglePlaces));
        $this->assertInstanceOf(ApifyProspectSource::class, $resolver->resolve(ProspectDataSource::Apify));
    }

    public function test_profile_stores_data_source(): void
    {
        config(['prospect_search.apify.token' => 'test-token']);

        $company = Company::factory()->billingExempt()->create();

        $profile = ProspectSearchProfile::create([
            'company_id' => $company->id,
            'name' => 'Apify Test',
            'industries' => ['Heizung'],
            'data_source' => ProspectDataSource::Apify,
            'postal_code' => '10115',
            'radius_km' => 10,
            'max_results_per_run' => 10,
        ]);

        $this->assertSame(ProspectDataSource::Apify, $profile->fresh()->data_source);
    }

    public function test_apify_source_normalizes_places_from_dataset(): void
    {
        config([
            'prospect_search.apify.token' => 'test-token',
            'prospect_search.apify.actor_id' => 'compass/crawler-google-places',
            'prospect_search.api_key' => 'google-test',
        ]);

        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'results' => [[
                    'geometry' => ['location' => ['lat' => 52.52, 'lng' => 13.4]],
                    'address_components' => [
                        ['long_name' => 'Berlin', 'types' => ['locality']],
                    ],
                ]],
            ], 200),
            'api.apify.com/v2/acts/*' => Http::response(['data' => ['id' => 'run_123']], 200),
            'api.apify.com/v2/actor-runs/*' => Http::response([
                'data' => ['status' => 'SUCCEEDED', 'defaultDatasetId' => 'dataset_123'],
            ], 200),
            'api.apify.com/v2/datasets/*' => Http::response([
                [
                    'placeId' => 'ChIJtest',
                    'title' => 'Müller Heizung GmbH',
                    'address' => 'Musterstraße 1, 10115 Berlin',
                    'phoneUnformatted' => '+4930123456',
                    'url' => 'https://maps.google.com/test',
                    'categoryName' => 'Heating contractor',
                    'location' => ['lat' => 52.52, 'lng' => 13.4],
                ],
            ], 200),
        ]);

        $results = app(ApifyProspectSource::class)->search('10115', 10, ['Heizung'], 5);

        $this->assertCount(1, $results);
        $this->assertSame('ChIJtest', $results->first()['google_place_id']);
        $this->assertSame('Müller Heizung GmbH', $results->first()['company_name']);
        $this->assertSame('apify_google_maps', $results->first()['source']);
    }
}
