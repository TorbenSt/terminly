<?php

namespace App\DTOs;

use App\Enums\StaffCustomerBinding;
use Illuminate\Support\Collection;

readonly class SchedulingContext
{
    public function __construct(
        public int $companyId,
        public Collection $clusters,
        public Collection $staff,
        public Collection $negotiationFeedback,
        public Collection $existingAppointments,
        public StaffCustomerBinding $staffCustomerBinding = StaffCustomerBinding::Prefer,
    ) {}

    public function toAiPayload(): array
    {
        return [
            'company_id' => $this->companyId,
            'staff_customer_binding' => $this->staffCustomerBinding->value,
            'clusters' => $this->clusters->values()->all(),
            'staff' => $this->staff->values()->all(),
            'negotiation_feedback' => $this->negotiationFeedback->values()->all(),
            'existing_appointments' => $this->existingAppointments->values()->all(),
        ];
    }
}
