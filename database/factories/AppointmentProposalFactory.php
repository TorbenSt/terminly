<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\AppointmentProposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentProposal>
 */
class AppointmentProposalFactory extends Factory
{
    protected $model = AppointmentProposal::class;

    public function definition(): array
    {
        $base = now()->addDays(3)->setTime(9, 0);

        return [
            'appointment_id' => Appointment::factory(),
            'round' => 1,
            'option_1_at' => $base->copy(),
            'option_2_at' => $base->copy()->addHours(2),
            'option_3_at' => $base->copy()->addDays(1)->setTime(14, 0),
            'staff_member_id' => null,
            'selected_option' => null,
        ];
    }
}
