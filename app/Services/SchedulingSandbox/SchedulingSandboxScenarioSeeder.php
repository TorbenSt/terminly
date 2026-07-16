<?php

namespace App\Services\SchedulingSandbox;

use App\Enums\AppointmentStatus;
use App\Enums\SchedulingSandboxScenario;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Customer;
use App\Models\RecurringService;
use App\Models\ServiceType;
use App\Models\StaffAvailability;
use App\Models\StaffMember;
use Carbon\Carbon;

class SchedulingSandboxScenarioSeeder
{
    public function seed(Company $company, SchedulingSandboxScenario $scenario): array
    {
        return match ($scenario) {
            SchedulingSandboxScenario::SimpleMaintenance,
            SchedulingSandboxScenario::GrokFallback => $this->seedSimpleMaintenance($company),
            SchedulingSandboxScenario::RegionalTwoStaff => $this->seedRegionalTwoStaff($company),
            SchedulingSandboxScenario::RegionalTour => $this->seedRegionalTour($company),
            SchedulingSandboxScenario::StaffQualification => $this->seedStaffQualification($company),
            SchedulingSandboxScenario::RealLifeCapacity => $this->seedRealLifeCapacity($company),
        };
    }

    /**
     * @return array<string, int>
     */
    private function seedSimpleMaintenance(Company $company): array
    {
        $maintenance = $this->createServiceType($company, 'Wartung', 45, true, 7);
        $staff = $this->createStaff($company, 'Max Techniker', [$maintenance->id]);
        $this->weekdayAvailability($staff);

        $customer = $this->createCustomer($company, '10115');
        $this->createDueRecurring($company, $customer, $maintenance);

        return [
            'staff' => 1,
            'customers' => 1,
            'due_services' => 1,
            'confirmed_appointments' => 0,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function seedRegionalTwoStaff(Company $company): array
    {
        $maintenance = $this->createServiceType($company, 'Wartung', 45, true, 7);

        $staffA = $this->createStaff($company, 'Techniker Nord', [$maintenance->id]);
        $staffB = $this->createStaff($company, 'Techniker Süd', [$maintenance->id]);
        $this->weekdayAvailability($staffA);
        $this->weekdayAvailability($staffB);

        $plzGroups = [
            ['10115', '10117'],
            ['20095', '20144'],
        ];

        $dueCount = 0;
        foreach ($plzGroups as $group) {
            foreach ($group as $plz) {
                $customer = $this->createCustomer($company, $plz);
                $this->createDueRecurring($company, $customer, $maintenance);
                $dueCount++;
            }
        }

        return [
            'staff' => 2,
            'customers' => 4,
            'due_services' => $dueCount,
            'confirmed_appointments' => 0,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function seedRegionalTour(Company $company): array
    {
        $maintenance = $this->createServiceType($company, 'Wartung', 45, true, 7);
        $staff = $this->createStaff($company, 'Max Techniker', [$maintenance->id]);
        $this->weekdayAvailability($staff);

        $tuesday = now()->addWeek()->startOfDay();
        while ($tuesday->dayOfWeek !== Carbon::TUESDAY) {
            $tuesday->addDay();
        }
        $wednesday = $tuesday->copy()->addDay();

        $berlinTour = [
            ['time' => '09:00', 'plz' => '10115'],
            ['time' => '13:00', 'plz' => '10119'],
        ];

        foreach ($berlinTour as $stop) {
            $customer = $this->createCustomer($company, $stop['plz']);
            Appointment::create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'service_type_id' => $maintenance->id,
                'staff_member_id' => $staff->id,
                'status' => AppointmentStatus::Confirmed,
                'scheduled_at' => $tuesday->copy()->setTimeFromTimeString($stop['time']),
                'duration_minutes' => 60,
                'travel_time_minutes' => 15,
            ]);
        }

        $hamburgTour = [
            ['time' => '09:00', 'plz' => '20095'],
            ['time' => '14:00', 'plz' => '20097'],
        ];

        foreach ($hamburgTour as $stop) {
            $customer = $this->createCustomer($company, $stop['plz']);
            Appointment::create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'service_type_id' => $maintenance->id,
                'staff_member_id' => $staff->id,
                'status' => AppointmentStatus::Confirmed,
                'scheduled_at' => $wednesday->copy()->setTimeFromTimeString($stop['time']),
                'duration_minutes' => 60,
                'travel_time_minutes' => 15,
            ]);
        }

        $dueCustomer = $this->createCustomer($company, '10178');
        $this->createDueRecurring($company, $dueCustomer, $maintenance);

        return [
            'staff' => 1,
            'customers' => 6,
            'due_services' => 1,
            'confirmed_appointments' => 4,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function seedStaffQualification(Company $company): array
    {
        $maintenance = $this->createServiceType($company, 'Wartung', 45, true, 7);
        $inspection = $this->createServiceType($company, 'Inspektion', 90, true, 30);

        $maintenanceStaff = $this->createStaff($company, 'Wartungs-Techniker', [$maintenance->id]);
        $inspectionStaff = $this->createStaff($company, 'Inspektions-Techniker', [$inspection->id]);
        $this->weekdayAvailability($maintenanceStaff);
        $this->weekdayAvailability($inspectionStaff);

        $customer = $this->createCustomer($company, '30159');
        $this->createDueRecurring($company, $customer, $maintenance);

        return [
            'staff' => 2,
            'customers' => 1,
            'due_services' => 1,
            'confirmed_appointments' => 0,
        ];
    }

    /**
     * Real-life stress test: many staff, sparse qualifications, dense calendars across PLZ clusters.
     *
     * @return array<string, int>
     */
    private function seedRealLifeCapacity(Company $company): array
    {
        $maintenance = $this->createServiceType($company, 'Wartung', 60, true, 90);
        $inspection = $this->createServiceType($company, 'Inspektion', 90, true, 180);

        $qualified = [
            $this->createStaff($company, 'Anna Wartung', [$maintenance->id]),
            $this->createStaff($company, 'Ben Wartung', [$maintenance->id]),
        ];
        $unqualified = [
            $this->createStaff($company, 'Carla Inspektion', [$inspection->id]),
            $this->createStaff($company, 'David Inspektion', [$inspection->id]),
            $this->createStaff($company, 'Eva Junior', [$inspection->id]),
        ];

        foreach ([...$qualified, ...$unqualified] as $staff) {
            $this->weekdayAvailability($staff);
        }

        $plzClusters = [
            ['10115', '10117', '10119'], // Berlin
            ['20095', '20097', '20144'], // Hamburg
            ['80331', '80335', '80336'], // München
            ['50667', '50668', '50670'], // Köln
            ['60311', '60313', '60316'], // Frankfurt
            ['70173', '70174', '70176'], // Stuttgart
        ];

        $customersByCluster = [];
        foreach ($plzClusters as $clusterIndex => $plzs) {
            $customersByCluster[$clusterIndex] = [];
            foreach ($plzs as $plz) {
                $customersByCluster[$clusterIndex][] = $this->createCustomer($company, $plz);
            }
        }

        $dueCustomer = $this->createCustomer($company, '10178');
        $this->createDueRecurring($company, $dueCustomer, $maintenance);

        $confirmed = $this->seedBusyCalendars(
            $company,
            $qualified,
            $unqualified,
            $customersByCluster,
            $maintenance,
            $inspection,
        );

        return [
            'staff' => 5,
            'customers' => 1 + array_sum(array_map('count', $customersByCluster)),
            'due_services' => 1,
            'confirmed_appointments' => $confirmed,
            'plz_clusters' => count($plzClusters),
            'qualified_staff' => count($qualified),
        ];
    }

    /**
     * Fill staff calendars ~60–70% over the next 4 months.
     *
     * @param  list<StaffMember>  $qualified
     * @param  list<StaffMember>  $unqualified
     * @param  array<int, list<Customer>>  $customersByCluster
     */
    private function seedBusyCalendars(
        Company $company,
        array $qualified,
        array $unqualified,
        array $customersByCluster,
        ServiceType $maintenance,
        ServiceType $inspection,
    ): int {
        // ~65% of an 8h day with 60–90 min blocks ≈ 3–4 appointments/day
        $slotPatterns = [
            ['08:00', '10:00', '13:00'],
            ['08:00', '09:30', '11:30', '14:00'],
            ['09:00', '11:00', '13:30'],
            ['08:00', '10:30', '12:30', '14:30'],
        ];

        $clusterCount = count($customersByCluster);
        $rows = [];
        $now = now();
        $cursor = $now->copy()->startOfDay();
        $end = $now->copy()->addMonths(4)->endOfDay();
        $dayIndex = 0;

        while ($cursor->lte($end)) {
            if ($cursor->isWeekday()) {
                // Keep ~65% load overall: denser mid-week, lighter Fridays (still some load).
                $pattern = $cursor->isFriday()
                    ? ['08:00', '11:00']
                    : $slotPatterns[$dayIndex % count($slotPatterns)];

                foreach ($qualified as $staffIndex => $staff) {
                    $clusterIndex = ($staffIndex + $dayIndex) % $clusterCount;
                    $this->appendDayAppointments(
                        $rows,
                        $company,
                        $staff,
                        $customersByCluster[$clusterIndex],
                        $maintenance,
                        $cursor,
                        $pattern,
                        60,
                    );
                }

                foreach ($unqualified as $staffIndex => $staff) {
                    $clusterIndex = ($staffIndex + $dayIndex + 2) % $clusterCount;
                    $this->appendDayAppointments(
                        $rows,
                        $company,
                        $staff,
                        $customersByCluster[$clusterIndex],
                        $inspection,
                        $cursor,
                        $pattern,
                        90,
                    );
                }

                $dayIndex++;
            }

            $cursor->addDay();
        }

        foreach (array_chunk($rows, 250) as $chunk) {
            // Eloquent casting keeps scheduled_at consistent with other lab seeders.
            Appointment::query()->insert($chunk);
        }

        return count($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<Customer>  $customers
     * @param  list<string>  $times
     */
    private function appendDayAppointments(
        array &$rows,
        Company $company,
        StaffMember $staff,
        array $customers,
        ServiceType $serviceType,
        Carbon $day,
        array $times,
        int $durationMinutes,
    ): void {
        $timestamp = now()->toDateTimeString();

        foreach ($times as $index => $time) {
            $customer = $customers[$index % count($customers)];

            // Same pattern as RegionalTour: wall-clock on the Carbon day used throughout the lab.
            $scheduledAt = $day->copy()->setTimeFromTimeString($time);

            $rows[] = [
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'service_type_id' => $serviceType->id,
                'staff_member_id' => $staff->id,
                'recurring_service_id' => null,
                'status' => AppointmentStatus::Confirmed->value,
                'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
                'duration_minutes' => $durationMinutes,
                'travel_time_minutes' => 15,
                'notes' => null,
                'public_token' => bin2hex(random_bytes(24)),
                'negotiation_round' => 0,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }
    }

    private function createServiceType(
        Company $company,
        string $name,
        int $duration,
        bool $recurring,
        int $intervalDays,
    ): ServiceType {
        return ServiceType::create([
            'company_id' => $company->id,
            'name' => $name,
            'duration_minutes' => $duration,
            'is_recurring' => $recurring,
            'interval_days' => $intervalDays,
            'is_active' => true,
        ]);
    }

    /**
     * @param  list<int>  $serviceTypeIds
     */
    private function createStaff(Company $company, string $name, array $serviceTypeIds): StaffMember
    {
        $staff = StaffMember::create([
            'company_id' => $company->id,
            'name' => $name,
            'email' => strtolower(str_replace([' ', 'ä', 'ö', 'ü'], ['.', 'ae', 'oe', 'ue'], $name))."@{$company->slug}.test",
            'buffer_minutes' => 15,
            'is_active' => true,
        ]);
        $staff->serviceTypes()->sync($serviceTypeIds);

        return $staff;
    }

    private function weekdayAvailability(StaffMember $staff): void
    {
        foreach (range(1, 5) as $day) {
            StaffAvailability::create([
                'staff_member_id' => $staff->id,
                'day_of_week' => $day,
                'start_time' => '08:00:00',
                'end_time' => '16:00:00',
            ]);
        }
    }

    private function createCustomer(Company $company, string $postalCode): Customer
    {
        $index = $company->customers()->count() + 1;

        return Customer::factory()->create([
            'company_id' => $company->id,
            'name' => fake()->name(),
            'email' => "sandbox-kunde{$index}@{$company->slug}.test",
            'postal_code' => $postalCode,
        ]);
    }

    private function createDueRecurring(Company $company, Customer $customer, ServiceType $type): RecurringService
    {
        return RecurringService::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'service_type_id' => $type->id,
            'last_completed_at' => now()->subDays($type->interval_days ?? 14),
            'next_due_at' => now()->subDay(),
            'is_active' => true,
        ]);
    }
}
