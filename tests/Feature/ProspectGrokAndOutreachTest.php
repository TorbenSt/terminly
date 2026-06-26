<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CustomerProspect;
use App\Models\Plan;
use App\Models\ProspectOutreachEmail;
use App\Models\ProspectSearchProfile;
use App\Models\User;
use App\Services\Prospect\GrokCollectionsService;
use App\Services\Prospect\ProspectOutreachLimitService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProspectGrokAndOutreachTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function companyAdmin(Company $company): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole('company_admin');

        return $user;
    }

    public function test_grok_collection_search_uses_hybrid_mode(): void
    {
        Http::fake([
            'management-api.x.ai/v1/collections' => Http::response([
                'collections' => [
                    ['collection_name' => 'appointment-prospect-feedback-1', 'collection_id' => 'collection_abc'],
                ],
            ], 200),
            'api.x.ai/v1/documents/search' => Http::response([
                'matches' => [
                    ['content' => '{"action":"rejected","reason":"Zu klein","context":"rejected — Zu klein"}'],
                ],
            ], 200),
        ]);

        config([
            'grok.xai.management_api_key' => 'xai-mgmt-test-key',
            'grok.xai.api_key' => 'xai-test-key',
        ]);

        $service = app(GrokCollectionsService::class);
        $results = $service->search('collection_abc', 'Heizung Sanitär');

        $this->assertCount(1, $results);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/documents/search')
                && ($request->data()['retrieval_mode']['type'] ?? null) === 'hybrid';
        });
    }

    public function test_outreach_blocked_when_daily_limit_reached(): void
    {
        config(['prospect_search.outreach_rate_limit_per_day' => 1]);

        $company = Company::factory()->billingExempt()->create();
        $admin = $this->companyAdmin($company);

        $prospect = CustomerProspect::create([
            'company_id' => $company->id,
            'company_name' => 'Test GmbH',
            'email' => 'kontakt@test.de',
            'status' => 'new',
        ]);

        ProspectOutreachEmail::create([
            'company_id' => $company->id,
            'customer_prospect_id' => $prospect->id,
            'subject' => 'Hallo',
            'body_snapshot' => 'Text',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('prospects.outreach', $prospect), [
                'subject' => 'Nochmal',
                'body' => 'Inhalt',
            ])
            ->assertSessionHas('error');

        $this->assertEquals(1, ProspectOutreachEmail::count());
    }

    public function test_plan_outreach_limit_overrides_env_default(): void
    {
        config(['prospect_search.outreach_rate_limit_per_day' => 20]);

        $plan = Plan::factory()->create(['prospect_outreach_limit_per_day' => 5]);
        $company = Company::factory()->create(['plan_id' => $plan->id]);

        $service = app(ProspectOutreachLimitService::class);

        $this->assertSame(5, $service->dailyLimit($company));
    }

    public function test_feedback_record_dispatches_collection_sync_job(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $company = Company::factory()->create();
        $profile = ProspectSearchProfile::create([
            'company_id' => $company->id,
            'name' => 'Test',
            'industries' => ['Heizung'],
            'postal_code' => '10115',
            'radius_km' => 10,
            'max_results_per_run' => 10,
        ]);

        app(\App\Services\Prospect\ProspectFeedbackService::class)->record(
            $company,
            \App\Enums\ProspectFeedbackAction::Rejected,
            null,
            null,
            'Passt nicht',
        );

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\SyncProspectFeedbackToCollectionJob::class);
    }
}
