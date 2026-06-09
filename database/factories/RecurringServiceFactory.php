<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Customer;
use App\Models\RecurringService;
use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringService>
 */
class RecurringServiceFactory extends Factory
{
    protected $model = RecurringService::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'customer_id' => Customer::factory(),
            'service_type_id' => ServiceType::factory(),
            'last_completed_at' => now()->subMonth(),
            'next_due_at' => now()->subDays(fake()->numberBetween(0, 5)),
            'is_active' => true,
        ];
    }

    public function due(): static
    {
        return $this->state(fn () => [
            'next_due_at' => now()->subDay(),
        ]);
    }
}
