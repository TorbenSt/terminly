<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\RecurringService;
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

    /**
     * Pick the best qualified staff member: lowest workload, then highest regional affinity.
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

        $scored = $qualified->map(function (array $staff) use ($companyId, $postalCode, $batchAssignments) {
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
     * @return Collection<int, array<string, mixed>>
     */
    public function rebalanceAssignments(
        Collection $assignments,
        Collection $staffPool,
        int $companyId,
        array $postalByCustomer = [],
    ): Collection {
        $batchAssignments = [];

        return $assignments->map(function (array $assignment) use ($staffPool, $companyId, $postalByCustomer, &$batchAssignments) {
            $serviceTypeId = $this->resolveServiceTypeId($assignment);
            $customerId = $assignment['customer_id'] ?? null;
            $postalCode = $customerId ? ($postalByCustomer[$customerId] ?? null) : null;

            $picked = $this->pickQualifiedStaff(
                $staffPool,
                $serviceTypeId,
                $companyId,
                $postalCode,
                $batchAssignments,
            );

            if ($picked) {
                $assignment['staff_id'] = $picked['staff_id'];
            }

            return $assignment;
        })->values();
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
