<?php

namespace App\Services;

use App\DTOs\StaffBindingPreference;
use App\Enums\AppointmentStatus;
use App\Enums\DeadlinePhase;
use App\Enums\StaffCustomerBinding;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Customer;
use App\Models\RecurringService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class StaffLoadBalancerService
{
    public function __construct(
        private readonly ClusteringService $clustering,
        private readonly AvailabilityService $availability,
    ) {}

    /**
     * Count confirmed/proposed appointments in the planning horizon.
     */
    public function upcomingWorkload(int $staffMemberId, ?int $companyId = null, ?int $horizonDays = null): int
    {
        $horizonDays ??= (int) config('scheduling.ai_appointment_horizon_days', 90);
        $from = $this->availability->earliestBookableDate();
        $to = now()->addDays($horizonDays)->endOfDay();

        $query = Appointment::query()
            ->where('staff_member_id', $staffMemberId)
            ->whereIn('status', [AppointmentStatus::Confirmed, AppointmentStatus::Proposed])
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$from, $to]);

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        return $query->count();
    }

    /**
     * How often this staff already serves the customer's PLZ region in the horizon.
     */
    public function regionalAffinity(int $staffMemberId, string $postalCode, ?int $horizonDays = null): int
    {
        $horizonDays ??= (int) config('scheduling.ai_appointment_horizon_days', 90);
        $from = $this->availability->earliestBookableDate();
        $to = now()->addDays($horizonDays)->endOfDay();
        $region = $this->clustering->regionKey($postalCode);

        return Appointment::query()
            ->where('staff_member_id', $staffMemberId)
            ->whereIn('status', [AppointmentStatus::Confirmed, AppointmentStatus::Proposed])
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$from, $to])
            ->with('customer:id,postal_code')
            ->get()
            ->filter(fn (Appointment $appointment) => $this->clustering->regionKey($appointment->customer->postal_code) === $region)
            ->count();
    }

    public function deadlinePhase(Carbon|string|null $nextDueAt, int $completionWindowDays): DeadlinePhase
    {
        if ($nextDueAt === null || $completionWindowDays <= 0) {
            return DeadlinePhase::Green;
        }

        $due = $nextDueAt instanceof Carbon ? $nextDueAt->copy() : Carbon::parse($nextDueAt);
        $windowEnd = $due->copy()->startOfDay()->addDays($completionWindowDays);
        $remaining = (int) now()->startOfDay()->diffInDays($windowEnd, false);

        if ($remaining <= 0) {
            return DeadlinePhase::Red;
        }

        $ratio = $remaining / $completionWindowDays;

        // Red: last quarter of the window (and always when ≤7 days on longer SLAs).
        if ($ratio <= 0.25 || ($completionWindowDays > 14 && $remaining <= 7)) {
            return DeadlinePhase::Red;
        }

        if ($ratio <= 0.5) {
            return DeadlinePhase::Yellow;
        }

        return DeadlinePhase::Green;
    }

    public function preferenceFor(
        Company $company,
        ?Customer $customer,
        ?Carbon $nextDueAt = null,
        ?int $completionWindowDays = null,
    ): StaffBindingPreference {
        $mode = $company->staff_customer_binding instanceof StaffCustomerBinding
            ? $company->staff_customer_binding
            : StaffCustomerBinding::tryFrom((string) ($company->staff_customer_binding ?? 'prefer'))
                ?? StaffCustomerBinding::Prefer;

        $window = $completionWindowDays
            ?? (int) config('scheduling.default_completion_window_days', 14);

        return new StaffBindingPreference(
            mode: $mode,
            primaryStaffId: $customer?->primary_staff_member_id,
            backupStaffId: $customer?->backup_staff_member_id,
            phase: $this->deadlinePhase($nextDueAt, $window),
        );
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @param  array<int, array{next_due_at?: mixed, completion_window_days?: int}>  $jobMetaByCustomer
     * @return array<int, StaffBindingPreference>
     */
    public function preferencesByCustomer(Company $company, Collection $customers, array $jobMetaByCustomer = []): array
    {
        $map = [];

        foreach ($customers as $customer) {
            $meta = $jobMetaByCustomer[$customer->id] ?? [];
            $map[$customer->id] = $this->preferenceFor(
                $company,
                $customer,
                isset($meta['next_due_at']) ? Carbon::parse($meta['next_due_at']) : null,
                isset($meta['completion_window_days']) ? (int) $meta['completion_window_days'] : null,
            );
        }

        return $map;
    }

    /**
     * Pick the best qualified staff member: binding rules, then workload, then regional affinity.
     *
     * @param  Collection<int, array<string, mixed>|Collection<string, mixed>>  $staffPool
     * @param  array<int, int>  $batchAssignments  staff_id => count assigned in this run
     * @return array<string, mixed>|null
     */
    public function pickQualifiedStaff(
        Collection $staffPool,
        mixed $serviceTypeId,
        int $companyId,
        ?string $postalCode = null,
        array &$batchAssignments = [],
        ?StaffBindingPreference $preference = null,
    ): ?array {
        $normalized = $staffPool
            ->map(fn ($staff) => $staff instanceof Collection ? $staff->all() : (array) $staff)
            ->values();

        $qualified = $normalized->filter(function (array $staff) use ($serviceTypeId) {
            if ($serviceTypeId === null) {
                return true;
            }

            $ids = collect($staff['qualified_service_type_ids'] ?? [])->map(fn ($id) => (int) $id);

            return $ids->contains((int) $serviceTypeId);
        })->values();

        if ($qualified->isEmpty()) {
            return $normalized->first();
        }

        $restricted = $this->applyBindingFilter($qualified, $preference);

        if ($restricted === null) {
            return null;
        }

        if ($restricted->count() === 1 && $this->forcesSingleStaff($preference)) {
            $winner = $restricted->first();
            $staffId = (int) $winner['staff_id'];
            $batchAssignments[$staffId] = ((int) ($batchAssignments[$staffId] ?? 0)) + 1;

            return $winner;
        }

        $scored = $restricted->map(function (array $staff) use ($companyId, $postalCode, $batchAssignments, $preference) {
            $staffId = (int) $staff['staff_id'];
            $workload = $staff['upcoming_workload'] ?? $this->upcomingWorkload($staffId, $companyId);
            $workload = (int) $workload + (int) ($batchAssignments[$staffId] ?? 0);

            $affinity = 0;
            if ($postalCode) {
                $region = $this->clustering->regionKey($postalCode);
                $affinity = (int) ($staff['regional_affinity'][$region]
                    ?? $this->regionalAffinity($staffId, $postalCode));
            }

            // Lower score is better: workload dominates, affinity breaks ties.
            $score = ($workload * 100) - min(50, $affinity);
            $score -= $this->preferenceBoost($staffId, $preference);

            return [
                'staff' => $staff,
                'score' => $score,
                'workload' => $workload,
                'affinity' => $affinity,
            ];
        })->sort(function (array $a, array $b) {
            if ($a['score'] !== $b['score']) {
                return $a['score'] <=> $b['score'];
            }

            return ((int) $a['staff']['staff_id']) <=> ((int) $b['staff']['staff_id']);
        })->values();

        $winner = $scored->first()['staff'] ?? null;

        if ($winner) {
            $staffId = (int) $winner['staff_id'];
            $batchAssignments[$staffId] = ((int) ($batchAssignments[$staffId] ?? 0)) + 1;
        }

        return $winner;
    }

    /**
     * Reassign staff on Grok (or other) assignments toward even load among qualified techs.
     *
     * @param  Collection<int, array<string, mixed>>  $assignments
     * @param  Collection<int, array<string, mixed>|Collection<string, mixed>>  $staffPool
     * @param  array<int, string>  $postalByCustomer
     * @param  array<int, StaffBindingPreference>  $preferencesByCustomer
     * @return Collection<int, array<string, mixed>>
     */
    public function rebalanceAssignments(
        Collection $assignments,
        Collection $staffPool,
        int $companyId,
        array $postalByCustomer = [],
        array $preferencesByCustomer = [],
    ): Collection {
        $batchAssignments = [];

        return $assignments->map(function (array $assignment) use (
            $staffPool,
            $companyId,
            $postalByCustomer,
            $preferencesByCustomer,
            &$batchAssignments,
        ) {
            $serviceTypeId = $this->resolveServiceTypeId($assignment);
            $customerId = $assignment['customer_id'] ?? null;
            $postalCode = $customerId ? ($postalByCustomer[$customerId] ?? null) : null;
            $preference = $customerId ? ($preferencesByCustomer[(int) $customerId] ?? null) : null;

            $picked = $this->pickQualifiedStaff(
                $staffPool,
                $serviceTypeId,
                $companyId,
                $postalCode,
                $batchAssignments,
                $preference,
            );

            if ($picked) {
                $assignment['staff_id'] = $picked['staff_id'];
            }

            return $assignment;
        })->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $qualified
     * @return Collection<int, array<string, mixed>>|null  null = hard binding impossible
     */
    private function applyBindingFilter(Collection $qualified, ?StaffBindingPreference $preference): ?Collection
    {
        if (! $preference || $preference->mode === StaffCustomerBinding::Off || ! $preference->hasPreferredStaff()) {
            return $qualified;
        }

        $primary = $this->findStaff($qualified, $preference->primaryStaffId);
        $backup = $this->findStaff($qualified, $preference->backupStaffId);

        return match ($preference->mode) {
            StaffCustomerBinding::Hard => $primary ?? $backup
                ? collect([$primary ?? $backup])->values()
                : null,
            StaffCustomerBinding::StrictWithExceptions => match ($preference->phase) {
                DeadlinePhase::Green => $primary ?? $backup
                    ? collect([$primary ?? $backup])->values()
                    : null,
                DeadlinePhase::Yellow, DeadlinePhase::Red => $qualified,
            },
            StaffCustomerBinding::Prefer => $qualified,
            StaffCustomerBinding::Off => $qualified,
        };
    }

    private function forcesSingleStaff(?StaffBindingPreference $preference): bool
    {
        if (! $preference || ! $preference->hasPreferredStaff()) {
            return false;
        }

        return $preference->mode === StaffCustomerBinding::Hard
            || ($preference->mode === StaffCustomerBinding::StrictWithExceptions
                && $preference->phase === DeadlinePhase::Green);
    }

    private function preferenceBoost(int $staffId, ?StaffBindingPreference $preference): int
    {
        if (! $preference || $preference->mode === StaffCustomerBinding::Off) {
            return 0;
        }

        $isPrimary = $preference->primaryStaffId === $staffId;
        $isBackup = $preference->backupStaffId === $staffId;

        if (! $isPrimary && ! $isBackup) {
            return 0;
        }

        $primaryBoost = match ($preference->mode) {
            StaffCustomerBinding::Prefer => 10_000,
            StaffCustomerBinding::StrictWithExceptions => match ($preference->phase) {
                DeadlinePhase::Green => 10_000,
                DeadlinePhase::Yellow => 5_000,
                DeadlinePhase::Red => 500,
            },
            StaffCustomerBinding::Hard => 10_000,
            StaffCustomerBinding::Off => 0,
        };

        if ($isPrimary) {
            return $primaryBoost;
        }

        // Backup is second choice: slightly less than primary, still above load balancing.
        return (int) max(100, (int) ($primaryBoost * 0.5));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $pool
     * @return array<string, mixed>|null
     */
    private function findStaff(Collection $pool, ?int $staffId): ?array
    {
        if ($staffId === null) {
            return null;
        }

        return $pool->first(fn (array $staff) => (int) $staff['staff_id'] === $staffId);
    }

    /**
     * @param  array<string, mixed>  $assignment
     */
    private function resolveServiceTypeId(array $assignment): ?int
    {
        if (isset($assignment['service_type_id'])) {
            return (int) $assignment['service_type_id'];
        }

        if (isset($assignment['recurring_id'])) {
            $serviceTypeId = RecurringService::query()
                ->whereKey((int) $assignment['recurring_id'])
                ->value('service_type_id');

            return $serviceTypeId !== null ? (int) $serviceTypeId : null;
        }

        return null;
    }
}
