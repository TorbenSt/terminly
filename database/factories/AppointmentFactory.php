<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'customer_id' => Customer::factory(),
            'service_type_id' => ServiceType::factory(),
            'staff_member_id' => null,
            'recurring_service_id' => null,
            'status' => AppointmentStatus::Proposed,
            'scheduled_at' => null,
            'duration_minutes' => 60,
            'travel_time_minutes' => 15,
            'notes' => null,
            'negotiation_round' => 0,
        ];
    }
}
