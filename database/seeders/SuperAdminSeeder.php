<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::firstOrCreate(
            ['email' => 'super@appointment.test'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'company_id' => null,
                'is_active' => true,
            ]
        );

        $superAdmin->assignRole('super_admin');

        Company::firstOrCreate(
            ['slug' => 'demo-wartung'],
            [
                'name' => 'Demo Wartung GmbH',
                'email' => 'info@demo-wartung.test',
                'phone' => '+49 30 1234567',
                'timezone' => 'Europe/Berlin',
                'is_active' => true,
            ]
        );

        Company::firstOrCreate(
            ['slug' => 'tech-service-nord'],
            [
                'name' => 'TechService Nord',
                'email' => 'kontakt@techservice-nord.test',
                'phone' => '+49 40 9876543',
                'timezone' => 'Europe/Berlin',
                'is_active' => true,
            ]
        );

        // Demo-Firmen sind dauerhaft von der Abrechnung befreit (Entwickler-/UI-Zugänge).
        Company::whereIn('slug', ['demo-wartung', 'tech-service-nord'])
            ->update(['billing_exempt' => true]);
    }
}
