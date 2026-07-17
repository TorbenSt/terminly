<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Database\Factories\ServiceTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceType extends Model
{
    /** @use HasFactory<ServiceTypeFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'duration_minutes',
        'is_recurring',
        'interval_days',
        'interval_months',
        'completion_window_days',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_recurring' => 'boolean',
            'is_active' => 'boolean',
            'completion_window_days' => 'integer',
        ];
    }

    public function staffMembers(): BelongsToMany
    {
        return $this->belongsToMany(StaffMember::class, 'staff_service_type');
    }

    public function recurringServices(): HasMany
    {
        return $this->hasMany(RecurringService::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
