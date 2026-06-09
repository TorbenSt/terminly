<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Traits\BelongsToCompany;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'customer_id',
        'service_type_id',
        'staff_member_id',
        'recurring_service_id',
        'status',
        'scheduled_at',
        'duration_minutes',
        'travel_time_minutes',
        'notes',
        'public_token',
        'negotiation_round',
    ];

    protected function casts(): array
    {
        return [
            'status' => AppointmentStatus::class,
            'scheduled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Appointment $appointment) {
            if (! $appointment->public_token) {
                $appointment->public_token = Str::random(48);
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    public function recurringService(): BelongsTo
    {
        return $this->belongsTo(RecurringService::class);
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(AppointmentProposal::class);
    }

    public function negotiations(): HasMany
    {
        return $this->hasMany(AppointmentNegotiation::class);
    }

    public function latestProposal(): ?AppointmentProposal
    {
        return $this->proposals()->latest('round')->first();
    }
}
