<?php

namespace Database\Seeders;

use App\Models\BillingSetting;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::firstOrCreate(
            ['name' => 'Basis'],
            [
                'description' => 'Das Basis-Abo für kleine Teams.',
                'price_cents' => 2900,
                'currency' => 'eur',
                'included_staff' => 5,
                'included_customers' => 100,
                'extra_staff_price_cents' => 500,
                'extra_customer_price_cents' => 50,
                'is_active' => true,
                'is_default' => true,
            ]
        );

        BillingSetting::firstOrCreate(
            ['key' => 'default_trial_days'],
            ['value' => (string) BillingSetting::DEFAULT_TRIAL_DAYS]
        );
    }
}
