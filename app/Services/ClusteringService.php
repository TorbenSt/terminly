<?php

namespace App\Services;

use App\DTOs\PostalCluster;
use App\Models\Company;
use App\Models\RecurringService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ClusteringService
{
    public function __construct(
        private readonly int $regionDigits = 3,
        private readonly ?AvailabilityService $availability = null,
    ) {}

    /**
     * @return Collection<int, PostalCluster>
     */
    public function clusterDueServices(Company $company, ?Carbon $dueBefore = null): Collection
    {
        $dueBefore ??= now()->endOfDay();

        $dueServices = RecurringService::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->where('next_due_at', '<=', $dueBefore)
            ->with(['customer', 'serviceType'])
            ->get();

        return $dueServices
            ->groupBy(fn (RecurringService $service) => $this->regionKey($service->customer->postal_code))
            ->map(function (Collection $group, string $region) {
                $jobs = $group->map(fn (RecurringService $service) => [
                    'recurring_id' => $service->id,
                    'customer_id' => $service->customer_id,
                    'postal_code' => $service->customer->postal_code,
                    'service_type_id' => $service->service_type_id,
                    'duration_min' => $service->serviceType->duration_minutes,
                    'next_due_at' => $service->next_due_at->toDateString(),
                ]);

                return new PostalCluster(
                    region: $region,
                    jobs: $jobs,
                    suggestedDate: $this->suggestClusterDate($group),
                );
            })
            ->sortByDesc(fn (PostalCluster $cluster) => $cluster->jobs->count())
            ->values();
    }

    public function regionKey(string $postalCode): string
    {
        return substr(preg_replace('/\D/', '', $postalCode) ?? '', 0, $this->regionDigits);
    }

    /**
     * @param  Collection<int, RecurringService>  $services
     */
    private function suggestClusterDate(Collection $services): string
    {
        $earliestDue = $services->min(fn (RecurringService $s) => $s->next_due_at);
        $availability = $this->availability ?? app(AvailabilityService::class);
        $after = $earliestDue
            ? Carbon::parse($earliestDue)->max(now())->startOfDay()
            : now()->startOfDay();

        // earliestBookableDate adds lead days from "after"; pass day before so due-today still gets tomorrow+.
        return $availability->earliestBookableDate($after->copy()->subDay())->toDateString();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function exportClustersForAi(Company $company): Collection
    {
        return $this->clusterDueServices($company)->map(fn (PostalCluster $cluster) => $cluster->toArray());
    }
}
