<?php

namespace App\Http\Controllers;

use App\Enums\ProspectDataSource;
use App\Enums\ProspectSearchTrigger;
use App\Jobs\SearchCustomerProspectsJob;
use App\Models\ProspectSearchProfile;
use App\Models\ProspectSearchRun;
use App\Services\Prospect\ProspectSearchDispatcher;
use App\Services\Prospect\ProspectSourceResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProspectSearchProfileController extends Controller
{
    public function __construct(protected ProspectSourceResolver $sources) {}

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ProspectSearchProfile::class);

        $company = $request->user()->company;
        $validated = $this->validated($request, $company);

        ProspectSearchProfile::create($validated);

        return back()->with('success', 'Suchprofil angelegt.');
    }

    public function update(Request $request, ProspectSearchProfile $profile): RedirectResponse
    {
        $this->authorize('update', $profile);

        $company = $request->user()->company;
        $profile->update($this->validated($request, $company));

        return back()->with('success', 'Suchprofil aktualisiert.');
    }

    public function destroy(ProspectSearchProfile $profile): RedirectResponse
    {
        $this->authorize('delete', $profile);
        $profile->delete();

        return back()->with('success', 'Suchprofil gelöscht.');
    }

    public function run(Request $request, ProspectSearchProfile $profile): RedirectResponse
    {
        $this->authorize('update', $profile);

        $company = $request->user()->company;

        if (! $company->hasProspectSearchAccess()) {
            return back()->with('error', 'Kundensuche ist nicht freigeschaltet.');
        }

        $maxResults = $profile->effectiveMaxResults($company->effectivePlan());

        $run = ProspectSearchRun::create([
            'company_id' => $company->id,
            'prospect_search_profile_id' => $profile->id,
            'trigger' => ProspectSearchTrigger::Manual,
            'data_source' => ($profile->data_source ?? ProspectDataSource::GooglePlaces)->value,
            'requested_max_results' => $maxResults,
        ]);

        ProspectSearchDispatcher::dispatch($run->id, manual: true);

        $source = ($profile->data_source ?? ProspectDataSource::GooglePlaces)->label();

        return back()->with('success', "Kundensuche gestartet ({$source}). Apify-Läufe können einige Minuten dauern.");
    }

    protected function validated(Request $request, $company): array
    {
        $planCap = $company->effectivePlan()?->max_prospect_results_per_run ?? config('prospect_search.max_results_cap', 100);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'industries' => ['required', 'array', 'min:1'],
            'industries.*' => ['required', 'string', 'max:100'],
            'ai_instructions' => ['nullable', 'string', 'max:2000'],
            'data_source' => ['required', Rule::enum(ProspectDataSource::class)],
            'postal_code' => ['required', 'string', 'max:10'],
            'radius_km' => ['required', 'integer', 'min:1', 'max:100'],
            'max_results_per_run' => ['required', 'integer', 'min:1', 'max:'.$planCap],
            'exclude_existing_customers' => ['boolean'],
            'is_active' => ['boolean'],
            'schedule_enabled' => ['boolean'],
            'schedule_cron' => ['nullable', 'string', 'max:64'],
        ]);

        $dataSource = ProspectDataSource::from($validated['data_source']);

        if (! $this->sources->resolve($dataSource)->isConfigured()) {
            throw ValidationException::withMessages([
                'data_source' => "Die Datenquelle „{$dataSource->label()}“ ist nicht konfiguriert.",
            ]);
        }

        return $validated;
    }
}
