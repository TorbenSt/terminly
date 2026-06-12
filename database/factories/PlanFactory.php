<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'price_cents' => 2900,
            'currency' => 'eur',
            'included_staff' => 5,
            'included_customers' => 100,
            'extra_staff_price_cents' => 500,
            'extra_customer_price_cents' => 50,
            'is_active' => true,
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    public function unlimited(): static
    {
        return $this->state(fn () => ['included_staff' => null, 'included_customers' => null]);
    }
}
