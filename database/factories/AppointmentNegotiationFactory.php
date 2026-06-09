<?php

namespace Database\Factories;

use App\Enums\NegotiationStatus;
use App\Models\Appointment;
use App\Models\AppointmentNegotiation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentNegotiation>
 */
class AppointmentNegotiationFactory extends Factory
{
    protected $model = AppointmentNegotiation::class;

    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'round' => 1,
            'customer_feedback' => fake()->paragraph(),
            'ai_summary' => null,
            'status' => NegotiationStatus::Pending,
        ];
    }
}
