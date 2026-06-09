<?php

namespace Database\Factories;

use App\Models\StaffAvailability;
use App\Models\StaffMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffAvailability>
 */
class StaffAvailabilityFactory extends Factory
{
    protected $model = StaffAvailability::class;

    public function definition(): array
    {
        return [
            'staff_member_id' => StaffMember::factory(),
            'day_of_week' => fake()->numberBetween(1, 5),
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ];
    }
}
