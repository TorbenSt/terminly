<?php

namespace App\Services\SchedulingSandbox;

use App\Enums\AppointmentStatus;
use App\Enums\SchedulingSandboxScenario;
use App\Enums\StaffCustomerBinding;
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
            SchedulingSandboxScenario::PreferredStaffBinding => $this->seedPreferredStaffBinding($company),
            SchedulingSandboxScenario::RealLifeCapacity => $this->seedRealLifeCapacity($company),
            SchedulingSandboxScenario::RealLifeMixed => $this->seedRealLifeMixed($company),
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
     * Preferred technician binding: strict with exceptions, green vs red deadline phases.
     *
     * @return array<string, int>
     */
    private function seedPreferredStaffBinding(Company $company): array
    {
        $company->update([
            'staff_customer_binding' => StaffCustomerBinding::StrictWithExceptions,
        ]);

        $maintenance = $this->createServiceType($company, 'Wartung', 45, true, 90, 14);

        $primary = $this->createStaff($company, 'Stamm Anna', [$maintenance->id]);
        $other = $this->createStaff($company, 'Frei Ben', [$maintenance->id]);
        $this->weekdayAvailability($primary);
        $this->weekdayAvailability($other);

        // Saturate the preferred technician so load balancing would normally pick Ben.
        $confirmed = 0;
        for ($i = 1; $i <= 12; $i++) {
            $day = now()->addWeekdays($i)->setTime(9, 0);
            $blockCustomer = $this->createCustomer($company, '10115');
            Appointment::create([
                'company_id' => $company->id,
                'customer_id' => $blockCustomer->id,
                'service_type_id' => $maintenance->id,
                'staff_member_id' => $primary->id,
                'status' => AppointmentStatus::Confirmed,
                'scheduled_at' => $day,
                'duration_minutes' => 60,
                'travel_time_minutes' => 0,
            ]);
            $confirmed++;
        }

        // Green: due yesterday, 14-day window → ~13 days remaining → only Stamm/Vertretung.
        $greenCustomer = $this->createCustomer($company, '10178');
        $greenCustomer->update([
            'primary_staff_member_id' => $primary->id,
            'backup_staff_member_id' => $other->id,
        ]);
        $this->createDueRecurring($company, $greenCustomer, $maintenance, now()->subDay());

        // Red: due 12 days ago, 14-day window → ~2 days remaining → other qualified allowed.
        $redCustomer = $this->createCustomer($company, '10119');
        $redCustomer->update([
            'primary_staff_member_id' => $primary->id,
        ]);
        $this->createDueRecurring($company, $redCustomer, $maintenance, now()->subDays(12));

        return [
            'staff' => 2,
            'customers' => 14,
            'due_services' => 2,
            'confirmed_appointments' => $confirmed,
            'primary_staff_id' => $primary->id,
            'other_staff_id' => $other->id,
            'green_customer_id' => $greenCustomer->id,
            'red_customer_id' => $redCustomer->id,
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
     * Dense mixed fleet: many techs, heterogeneous skills/hours, Stamm bindings, mixed SLAs.
     *
     * @return array<string, int>
     */
    private function seedRealLifeMixed(Company $company): array
    {
        // Deterministic skill/availability patterns so lab + tests stay reproducible.
        $company->update([
            'staff_customer_binding' => StaffCustomerBinding::Prefer,
        ]);

        $wartung = $this->createServiceType($company, 'Wartung', 60, true, 90, 14);
        $inspektion = $this->createServiceType($company, 'Inspektion', 90, true, 180, 21);
        $stoerung = $this->createServiceType($company, 'Störung', 45, true, 30, 7);
        $montage = $this->createServiceType($company, 'Montage', 120, true, 365, 30);

        $serviceTypes = [$wartung, $inspektion, $stoerung, $montage];
        $serviceIds = array_map(fn (ServiceType $type) => $type->id, $serviceTypes);

        $staffNames = [
            'Alex Berger', 'Birgit Braun', 'Chris Dietz', 'Dana Engel', 'Erik Faust',
            'Fiona Graf', 'Gregor Hahn', 'Hanna Ibel', 'Ivo Jung', 'Jana Klein',
            'Klaus Lang', 'Lena Marx', 'Moritz Neuhaus', 'Nina Orth', 'Otto Pohl',
            'Paula Quinn', 'Ralf Steiner', 'Sara Thiel', 'Tim Urban', 'Vera Wolff',
        ];

        /** @var list<StaffMember> $staffPool */
        $staffPool = [];
        foreach ($staffNames as $index => $name) {
            $skillCount = 1 + ($index % 3);
            $skills = [];
            for ($i = 0; $i < $skillCount; $i++) {
                $skills[] = $serviceIds[($index + $i * 2) % count($serviceIds)];
            }
            $skills = array_values(array_unique($skills));

            $staff = $this->createStaff($company, $name, $skills);
            $this->mixedAvailability($staff, $index % 3);
            $staffPool[] = $staff;
        }

        // Guarantee every service has at least 3 qualified technicians.
        foreach ($serviceTypes as $typeIndex => $type) {
            $qualifiedIds = [];
            foreach ($staffPool as $staff) {
                $staff->loadMissing('serviceTypes:id');
                if ($staff->serviceTypes->contains('id', $type->id)) {
                    $qualifiedIds[] = $staff->id;
                }
            }

            $needed = 3 - count($qualifiedIds);
            for ($i = 0; $i < $needed; $i++) {
                $candidate = $staffPool[($typeIndex * 3 + $i * 7) % count($staffPool)];
                if (! $candidate->serviceTypes->contains('id', $type->id)) {
                    $candidate->serviceTypes()->attach($type->id);
                    $candidate->unsetRelation('serviceTypes');
                    $candidate->load('serviceTypes:id');
                }
            }
        }

        foreach ($staffPool as $staff) {
            $staff->load('serviceTypes:id,name,duration_minutes');
        }

        $plzClusters = [
            ['10115', '10117', '10119', '10178'], // Berlin
            ['20095', '20097', '20144'], // Hamburg
            ['80331', '80335', '80336'], // München
            ['50667', '50668', '50670'], // Köln
            ['60311', '60313', '60316'], // Frankfurt
            ['70173', '70174', '70176'], // Stuttgart
            ['90402', '90403', '90408'], // Nürnberg
            ['04109', '04105', '04229'], // Leipzig
        ];

        $customersByCluster = [];
        foreach ($plzClusters as $clusterIndex => $plzs) {
            $customersByCluster[$clusterIndex] = [];
            foreach ($plzs as $plz) {
                $customersByCluster[$clusterIndex][] = $this->createCustomer($company, $plz);
            }
        }

        $confirmed = $this->seedMixedBusyCalendars(
            $company,
            $staffPool,
            $customersByCluster,
            $serviceTypes,
        );

        $staffFor = function (int $serviceTypeId) use ($staffPool): array {
            return array_values(array_filter(
                $staffPool,
                fn (StaffMember $staff) => $staff->serviceTypes->contains('id', $serviceTypeId),
            ));
        };

        $wartungStaff = $staffFor($wartung->id);
        $inspektionStaff = $staffFor($inspektion->id);
        $montageStaff = $staffFor($montage->id);

        $dueSpecs = [
            [
                'plz' => '10178',
                'type' => $wartung,
                'due' => now()->subDay(),
                'primary' => $wartungStaff[0] ?? $staffPool[0],
                'backup' => $wartungStaff[1] ?? null,
            ],
            [
                'plz' => '20095',
                'type' => $inspektion,
                'due' => now()->subDays(8),
                'primary' => $inspektionStaff[0] ?? $staffPool[1],
                'backup' => null,
            ],
            [
                'plz' => '80331',
                'type' => $stoerung,
                'due' => now()->subDays(5),
                'primary' => null,
                'backup' => null,
            ],
            [
                'plz' => '50667',
                'type' => $wartung,
                'due' => now()->subDays(12),
                'primary' => $wartungStaff[min(2, count($wartungStaff) - 1)] ?? $staffPool[2],
                'backup' => $wartungStaff[0] ?? null,
            ],
            [
                'plz' => '60311',
                'type' => $montage,
                'due' => now()->subDays(3),
                'primary' => $montageStaff[0] ?? $staffPool[3],
                'backup' => $montageStaff[1] ?? null,
            ],
        ];

        foreach ($dueSpecs as $spec) {
            $customer = $this->createCustomer($company, $spec['plz']);
            $customer->update([
                'primary_staff_member_id' => $spec['primary']?->id,
                'backup_staff_member_id' => $spec['backup']?->id,
            ]);
            $this->createDueRecurring($company, $customer, $spec['type'], $spec['due']);
        }

        $fillerCount = array_sum(array_map('count', $customersByCluster));

        return [
            'staff' => count($staffPool),
            'customers' => $fillerCount + count($dueSpecs),
            'due_services' => count($dueSpecs),
            'confirmed_appointments' => $confirmed,
            'plz_clusters' => count($plzClusters),
            'service_types' => count($serviceTypes),
            'stamm_bindings' => collect($dueSpecs)->filter(fn (array $spec) => $spec['primary'] !== null)->count(),
        ];
    }

    /**
     * @param  list<StaffMember>  $staffPool
     * @param  array<int, list<Customer>>  $customersByCluster
     * @param  list<ServiceType>  $serviceTypes
     */
    private function seedMixedBusyCalendars(
        Company $company,
        array $staffPool,
        array $customersByCluster,
        array $serviceTypes,
    ): int {
        $patternsByLoad = [
            'high' => [
                ['08:00', '10:00', '13:00'],
                ['08:00', '09:30', '11:30', '14:00'],
            ],
            'medium' => [
                ['09:00', '13:00'],
                ['08:00', '11:00', '14:30'],
            ],
            'low' => [
                ['10:00'],
                ['08:00', '14:00'],
            ],
        ];

        $clusterCount = count($customersByCluster);
        $rows = [];
        $now = now();
        $cursor = $now->copy()->startOfDay();
        $end = $now->copy()->addWeeks(8)->endOfDay();
        $dayIndex = 0;

        while ($cursor->lte($end)) {
            if ($cursor->isWeekday()) {
                foreach ($staffPool as $staffIndex => $staff) {
                    $load = match ($staffIndex % 3) {
                        0 => 'high',
                        1 => 'medium',
                        default => 'low',
                    };

                    // Skip ~30% of days for low-load techs.
                    if ($load === 'low' && ($dayIndex + $staffIndex) % 3 === 0) {
                        continue;
                    }

                    $patterns = $patternsByLoad[$load];
                    $pattern = $cursor->isFriday()
                        ? array_slice($patterns[0], 0, max(1, (int) floor(count($patterns[0]) / 2)))
                        : $patterns[$dayIndex % count($patterns)];

                    $qualifiedTypes = $staff->serviceTypes;
                    if ($qualifiedTypes->isEmpty()) {
                        continue;
                    }

                    $serviceType = $qualifiedTypes[$dayIndex % $qualifiedTypes->count()];
                    $clusterIndex = ($staffIndex + $dayIndex) % $clusterCount;

                    $this->appendDayAppointments(
                        $rows,
                        $company,
                        $staff,
                        $customersByCluster[$clusterIndex],
                        $serviceType,
                        $cursor,
                        $pattern,
                        (int) $serviceType->duration_minutes,
                    );
                }

                $dayIndex++;
            }

            $cursor->addDay();
        }

        foreach (array_chunk($rows, 250) as $chunk) {
            Appointment::query()->insert($chunk);
        }

        return count($rows);
    }

    private function mixedAvailability(StaffMember $staff, int $variant): void
    {
        $schedules = match ($variant) {
            0 => [ // classic full week
                [1, '08:00:00', '16:00:00'],
                [2, '08:00:00', '16:00:00'],
                [3, '08:00:00', '16:00:00'],
                [4, '08:00:00', '16:00:00'],
                [5, '08:00:00', '16:00:00'],
            ],
            1 => [ // late shift Tue–Fri
                [2, '10:00:00', '18:00:00'],
                [3, '10:00:00', '18:00:00'],
                [4, '10:00:00', '18:00:00'],
                [5, '10:00:00', '18:00:00'],
            ],
            default => [ // short week Mon–Wed
                [1, '08:00:00', '15:00:00'],
                [2, '08:00:00', '15:00:00'],
                [3, '08:00:00', '15:00:00'],
            ],
        };

        foreach ($schedules as [$day, $start, $end]) {
            StaffAvailability::create([
                'staff_member_id' => $staff->id,
                'day_of_week' => $day,
                'start_time' => $start,
                'end_time' => $end,
            ]);
        }
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
        int $completionWindowDays = 14,
    ): ServiceType {
        return ServiceType::create([
            'company_id' => $company->id,
            'name' => $name,
            'duration_minutes' => $duration,
            'is_recurring' => $recurring,
            'interval_days' => $intervalDays,
            'completion_window_days' => $completionWindowDays,
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

    private function createDueRecurring(
        Company $company,
        Customer $customer,
        ServiceType $type,
        ?Carbon $nextDueAt = null,
    ): RecurringService {
        $nextDueAt ??= now()->subDay();

        return RecurringService::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'service_type_id' => $type->id,
            'last_completed_at' => $nextDueAt->copy()->subDays($type->interval_days ?? 14),
            'next_due_at' => $nextDueAt,
            'is_active' => true,
        ]);
    }
}
