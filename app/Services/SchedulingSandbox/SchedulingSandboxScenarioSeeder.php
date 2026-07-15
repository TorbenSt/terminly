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
