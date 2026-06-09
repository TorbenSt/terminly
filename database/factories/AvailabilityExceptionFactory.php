<?php

namespace Database\Factories;

use App\Models\AvailabilityException;
use App\Models\StaffMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AvailabilityException>
 */
class AvailabilityExceptionFactory extends Factory
{
    protected $model = AvailabilityException::class;

    public function definition(): array
    {
        return [
            'staff_member_id' => StaffMember::factory(),
            'date' => fake()->dateTimeBetween('+1 week', '+2 months')->format('Y-m-d'),
            'is_available' => false,
            'start_time' => null,
            'end_time' => null,
            'reason' => 'Urlaub',
        ];
    }
}
