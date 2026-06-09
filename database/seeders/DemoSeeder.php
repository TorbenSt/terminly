<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Customer;
use App\Models\RecurringService;
use App\Models\ServiceType;
use App\Models\StaffAvailability;
use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $companies = Company::all();

        if ($companies->isEmpty()) {
            $this->call(SuperAdminSeeder::class);
            $companies = Company::all();
        }

        foreach ($companies as $company) {
            $this->seedCompany($company);
        }

        Company::firstOrCreate(
            ['slug' => 'sued-service'],
            [
                'name' => 'Süd Service AG',
                'email' => 'office@sued-service.test',
                'phone' => '+49 89 5551234',
                'timezone' => 'Europe/Berlin',
            ]
        );

        $sued = Company::where('slug', 'sued-service')->first();
        if ($sued && $sued->customers()->count() === 0) {
            $this->seedCompany($sued, 8);
        }
    }

    private function seedCompany(Company $company, int $customerCount = 18): void
    {
        $admin = User::firstOrCreate(
            ['email' => "admin@{$company->slug}.test"],
            [
                'name' => "Admin {$company->name}",
                'password' => Hash::make('password'),
                'company_id' => $company->id,
                'is_active' => true,
            ]
        );
        $admin->assignRole('company_admin');

        $weekly = ServiceType::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Wartung wöchentlich'],
            [
                'duration_minutes' => 45,
                'is_recurring' => true,
                'interval_days' => 7,
                'description' => 'Regelmäßige Gerätewartung',
            ]
        );

        $monthly = ServiceType::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Inspektion monatlich'],
            [
                'duration_minutes' => 90,
                'is_recurring' => true,
                'interval_months' => 1,
                'description' => 'Monatliche Anlageninspektion',
            ]
        );

        $once = ServiceType::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Einmaliger Reparaturtermin'],
            [
                'duration_minutes' => 120,
                'is_recurring' => false,
                'description' => 'Einmalige Reparatur',
            ]
        );

        $staffProfiles = collect([
            ['name' => 'Max Techniker', 'services' => [$weekly->id, $monthly->id]],
            ['name' => 'Lisa Monteurin', 'services' => [$weekly->id, $once->id]],
            ['name' => 'Tom Service', 'services' => [$monthly->id, $once->id]],
        ])->map(function (array $data) use ($company) {
            $staff = StaffMember::firstOrCreate(
                ['company_id' => $company->id, 'name' => $data['name']],
                ['email' => strtolower(str_replace(' ', '.', $data['name']))."@{$company->slug}.test", 'buffer_minutes' => 15]
            );
            $staff->serviceTypes()->sync($data['services']);

            foreach (range(1, 5) as $day) {
                StaffAvailability::firstOrCreate(
                    ['staff_member_id' => $staff->id, 'day_of_week' => $day],
                    ['start_time' => '08:00:00', 'end_time' => '16:00:00']
                );
            }

            $user = User::firstOrCreate(
                ['email' => $staff->email],
                [
                    'name' => $staff->name,
                    'password' => Hash::make('password'),
                    'company_id' => $company->id,
                ]
            );
            $user->assignRole('staff');
            $staff->update(['user_id' => $user->id]);

            return $staff;
        });

        $postalCodes = [
            '10115', '10117', '10119', '10178',
            '20095', '20144', '20354',
            '30159', '30161', '30625',
            '40210', '50667', '80331', '90402',
            '04103', '01067', '28195',
        ];

        $customers = collect();
        for ($i = 0; $i < $customerCount; $i++) {
            $customers->push(Customer::factory()->create([
                'company_id' => $company->id,
                'postal_code' => $postalCodes[$i % count($postalCodes)],
                'email' => "kunde{$i}@{$company->slug}.test",
            ]));
        }

        $serviceTypes = collect([$weekly, $monthly, $once]);

        $customers->each(function (Customer $customer, int $index) use ($company, $serviceTypes) {
            $type = $serviceTypes[$index % $serviceTypes->count()];

            if (! $type->is_recurring) {
                return;
            }

            RecurringService::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'customer_id' => $customer->id,
                    'service_type_id' => $type->id,
                ],
                [
                    'last_completed_at' => now()->subDays($type->interval_days ?? 30),
                    'next_due_at' => now()->subDays(random_int(0, 3)),
                    'is_active' => true,
                ]
            );
        });
    }
}
