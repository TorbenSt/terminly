<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'timezone' => 'Europe/Berlin',
            'is_active' => true,
            // Neue Firmen starten wie in der App mit laufendem Testzeitraum.
            'trial_ends_at' => now()->addDays(30),
        ];
    }

    public function billingExempt(): static
    {
        return $this->state(fn () => ['billing_exempt' => true, 'trial_ends_at' => null]);
    }

    public function expiredTrial(): static
    {
        return $this->state(fn () => ['trial_ends_at' => now()->subDay()]);
    }

    public function withoutTrial(): static
    {
        return $this->state(fn () => ['trial_ends_at' => null]);
    }
}
