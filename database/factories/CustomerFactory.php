<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        $postalCodes = ['10115', '10117', '20095', '20144', '30159', '30161', '40210', '50667', '80331', '90402'];

        return [
            'company_id' => Company::factory(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->streetAddress(),
            'postal_code' => fake()->randomElement($postalCodes),
            'city' => fake()->city(),
            'latitude' => fake()->latitude(47.0, 55.0),
            'longitude' => fake()->longitude(6.0, 15.0),
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
