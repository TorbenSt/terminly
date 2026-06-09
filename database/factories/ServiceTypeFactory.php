<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceType>
 */
class ServiceTypeFactory extends Factory
{
    protected $model = ServiceType::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->words(2, true),
            'duration_minutes' => fake()->randomElement([30, 45, 60, 90, 120]),
            'is_recurring' => false,
            'interval_days' => null,
            'interval_months' => null,
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function recurringMonthly(): static
    {
        return $this->state(fn () => [
            'is_recurring' => true,
            'interval_months' => 1,
        ]);
    }

    public function recurringWeekly(): static
    {
        return $this->state(fn () => [
            'is_recurring' => true,
            'interval_days' => 7,
        ]);
    }
}
