<?php

namespace App\Http\Controllers;

use App\Enums\ProspectDataSource;
use App\Enums\ProspectSearchRunStatus;
use App\Enums\ProspectStatus;
use App\Models\BillingSetting;
use App\Models\CustomerProspect;
use App\Models\ProspectSearchProfile;
use App\Models\ProspectSearchRun;
use App\Services\Prospect\ProspectOutreachLimitService;
use App\Services\Prospect\ProspectSourceResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProspectHubController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CustomerProspect::class);

        $company = $request->user()->company;
        $hasAccess = $company->hasProspectSearchAccess();
        $outreachLimits = app(ProspectOutreachLimitService::class);

        $status = $request->string('status')->toString();

        $prospects = $hasAccess
            ? CustomerProspect::query()
                ->when($status !== '', fn ($q) => $q->where('status', $status))
                ->orderByDesc('match_score')
                ->orderByDesc('discovered_at')
                ->paginate(20)
                ->withQueryString()
            : collect();

        $profiles = $hasAccess
            ? ProspectSearchProfile::query()->orderBy('name')->get()
            : collect();

        $recentRuns = $hasAccess
            ? ProspectSearchRun::query()
                ->with('profile:id,name,data_source')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (ProspectSearchRun $run) => [
                    'id' => $run->id,
                    'status' => $run->status->value,
                    'data_source' => $run->data_source ?? $run->profile?->data_source?->value,
                    'candidates_found' => $run->candidates_found,
                    'prospects_saved' => $run->prospects_saved,
                    'duplicates_skipped' => $run->duplicates_skipped,
                    'error_message' => $run->error_message,
                    'profile' => $run->profile ? ['name' => $run->profile->name] : null,
                ])
            : collect();

        $sourceResolver = app(ProspectSourceResolver::class);

        $activeRun = ProspectSearchRun::query()
            ->with('profile:id,data_source')
            ->whereIn('status', [ProspectSearchRunStatus::Pending, ProspectSearchRunStatus::Running])
            ->latest()
            ->first();

        return Inertia::render('Prospects/Index', [
            'hasAccess' => $hasAccess,
            'prospects' => $hasAccess ? $prospects : [],
            'profiles' => $profiles,
            'recentRuns' => $recentRuns,
            'stats' => $hasAccess ? [
                'new_count' => CustomerProspect::where('status', ProspectStatus::New)->count(),
                'contacted_count' => CustomerProspect::where('status', ProspectStatus::Contacted)->count(),
                'converted_count' => CustomerProspect::where('status', ProspectStatus::Converted)->count(),
            ] : null,
            'addon' => [
                'price_cents' => BillingSetting::prospectSearchPriceCents(),
                'plan_includes' => (bool) $company->effectivePlan()?->includes_prospect_search,
                'has_addon' => $company->hasProspectSearchAddon(),
            ],
            'filters' => [
                'status' => $request->string('status')->toString() ?: null,
            ],
            'activeRun' => $activeRun ? [
                'id' => $activeRun->id,
                'status' => $activeRun->status->value,
                'data_source' => $activeRun->data_source ?? $activeRun->profile?->data_source?->value,
                'error_message' => $activeRun->error_message,
            ] : null,
            'outreach' => $hasAccess ? [
                'daily_limit' => $outreachLimits->dailyLimit($company),
                'sent_today' => $outreachLimits->sentToday($company),
                'remaining_today' => $outreachLimits->remainingToday($company),
            ] : null,
            'dataSources' => collect(ProspectDataSource::cases())->map(fn (ProspectDataSource $source) => [
                'value' => $source->value,
                'label' => $source->label(),
                'configured' => $sourceResolver->resolve($source)->isConfigured(),
            ])->values(),
        ]);
    }
}
