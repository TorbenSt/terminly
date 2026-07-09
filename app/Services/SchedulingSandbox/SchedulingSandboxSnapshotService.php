<?php

namespace App\Services\SchedulingSandbox;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AvailabilityException;
use App\Models\Company;
use App\Models\Customer;
use App\Models\RecurringService;
use App\Models\ServiceType;
use App\Models\StaffAvailability;
use App\Models\StaffMember;
use Illuminate\Support\Facades\DB;

class SchedulingSandboxSnapshotService
{
    public function copyIntoSandbox(
        Company $sandbox,
        Company $source,
        bool $markDueToday = false,
        bool $anonymizeEmails = true,
    ): array {
        $maxCustomers = (int) config('scheduling_lab.snapshot_max_customers', 50);
        $maxAppointments = (int) config('scheduling_lab.snapshot_max_appointments', 100);

        $idMap = [
            'service_types' => [],
            'staff' => [],
            'customers' => [],
            'recurring' => [],
        ];

        return DB::transaction(function () use ($sandbox, $source, $markDueToday, $anonymizeEmails, $maxCustomers, $maxAppointments, &$idMap) {
            $serviceTypes = ServiceType::withoutGlobalScopes()
                ->where('company_id', $source->id)
                ->where('is_active', true)
                ->get();

            foreach ($serviceTypes as $type) {
                $clone = ServiceType::create([
                    'company_id' => $sandbox->id,
                    'name' => $type->name,
                    'duration_minutes' => $type->duration_minutes,
                    'is_recurring' => $type->is_recurring,
                    'interval_days' => $type->interval_days,
                    'interval_months' => $type->interval_months,
                    'description' => $type->description,
                    'is_active' => true,
                ]);
                $idMap['service_types'][$type->id] = $clone->id;
            }

            $staffMembers = StaffMember::withoutGlobalScopes()
                ->where('company_id', $source->id)
                ->where('is_active', true)
                ->with(['serviceTypes', 'availabilities', 'availabilityExceptions'])
                ->get();

            foreach ($staffMembers as $staff) {
                $clone = StaffMember::create([
                    'company_id' => $sandbox->id,
                    'name' => $staff->name,
                    'email' => $anonymizeEmails
                        ? "sandbox-staff-{$staff->id}@{$sandbox->slug}.test"
                        : $staff->email,
                    'phone' => $staff->phone,
                    'buffer_minutes' => $staff->buffer_minutes,
                    'is_active' => true,
                ]);

                $qualified = $staff->serviceTypes
                    ->map(fn ($t) => $idMap['service_types'][$t->id] ?? null)
                    ->filter()
                    ->values()
                    ->all();
                $clone->serviceTypes()->sync($qualified);

                foreach ($staff->availabilities as $availability) {
                    StaffAvailability::create([
                        'staff_member_id' => $clone->id,
                        'day_of_week' => $availability->day_of_week,
                        'start_time' => $availability->start_time,
                        'end_time' => $availability->end_time,
                        'break_start_time' => $availability->break_start_time,
                        'break_end_time' => $availability->break_end_time,
                    ]);
                }

                foreach ($staff->availabilityExceptions as $exception) {
                    AvailabilityException::create([
                        'staff_member_id' => $clone->id,
                        'date' => $exception->date,
                        'is_available' => $exception->is_available,
                        'start_time' => $exception->start_time,
                        'end_time' => $exception->end_time,
                    ]);
                }

                $idMap['staff'][$staff->id] = $clone->id;
            }

            $customers = Customer::withoutGlobalScopes()
                ->where('company_id', $source->id)
                ->where('is_active', true)
                ->orderBy('id')
                ->limit($maxCustomers)
                ->get();

            foreach ($customers as $customer) {
                $clone = Customer::create([
                    'company_id' => $sandbox->id,
                    'name' => $customer->name,
                    'email' => $anonymizeEmails
                        ? "sandbox-kunde-{$customer->id}@{$sandbox->slug}.test"
                        : $customer->email,
                    'phone' => $customer->phone,
                    'address' => $customer->address,
                    'postal_code' => $customer->postal_code,
                    'city' => $customer->city,
                    'latitude' => $customer->latitude,
                    'longitude' => $customer->longitude,
                    'notes' => $customer->notes,
                    'is_active' => true,
                ]);
                $idMap['customers'][$customer->id] = $clone->id;
            }

            $dueCount = 0;
            $recurringServices = RecurringService::withoutGlobalScopes()
                ->where('company_id', $source->id)
                ->where('is_active', true)
                ->whereIn('customer_id', array_keys($idMap['customers']))
                ->get();

            foreach ($recurringServices as $recurring) {
                $customerCloneId = $idMap['customers'][$recurring->customer_id] ?? null;
                $typeCloneId = $idMap['service_types'][$recurring->service_type_id] ?? null;

                if (! $customerCloneId || ! $typeCloneId) {
                    continue;
                }

                RecurringService::create([
                    'company_id' => $sandbox->id,
                    'customer_id' => $customerCloneId,
                    'service_type_id' => $typeCloneId,
                    'last_completed_at' => $recurring->last_completed_at,
                    'next_due_at' => $markDueToday ? now()->subDay() : $recurring->next_due_at,
                    'is_active' => true,
                ]);
                $dueCount++;
            }

            $confirmedCount = 0;
            $appointments = Appointment::withoutGlobalScopes()
                ->where('company_id', $source->id)
                ->where('status', AppointmentStatus::Confirmed)
                ->whereNotNull('scheduled_at')
                ->orderByDesc('scheduled_at')
                ->limit($maxAppointments)
                ->get();

            foreach ($appointments as $appointment) {
                $customerCloneId = $idMap['customers'][$appointment->customer_id] ?? null;
                $typeCloneId = $idMap['service_types'][$appointment->service_type_id] ?? null;
                $staffCloneId = $appointment->staff_member_id
                    ? ($idMap['staff'][$appointment->staff_member_id] ?? null)
                    : null;

                if (! $customerCloneId || ! $typeCloneId) {
                    continue;
                }

                Appointment::create([
                    'company_id' => $sandbox->id,
                    'customer_id' => $customerCloneId,
                    'service_type_id' => $typeCloneId,
                    'staff_member_id' => $staffCloneId,
                    'status' => AppointmentStatus::Confirmed,
                    'scheduled_at' => $appointment->scheduled_at,
                    'duration_minutes' => $appointment->duration_minutes,
                    'travel_time_minutes' => $appointment->travel_time_minutes,
                    'notes' => $appointment->notes,
                ]);
                $confirmedCount++;
            }

            return [
                'counts' => [
                    'service_types' => count($idMap['service_types']),
                    'staff' => count($idMap['staff']),
                    'customers' => count($idMap['customers']),
                    'recurring_services' => $dueCount,
                    'confirmed_appointments' => $confirmedCount,
                ],
                'source_company_id' => $source->id,
                'id_map_summary' => [
                    'service_types' => count($idMap['service_types']),
                    'staff' => count($idMap['staff']),
                    'customers' => count($idMap['customers']),
                ],
            ];
        });
    }
}
