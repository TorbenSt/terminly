<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Database\Factories\StaffMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffMember extends Model
{
    /** @use HasFactory<StaffMemberFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'name',
        'email',
        'phone',
        'buffer_minutes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function serviceTypes(): BelongsToMany
    {
        return $this->belongsToMany(ServiceType::class, 'staff_service_type');
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(StaffAvailability::class);
    }

    public function availabilityExceptions(): HasMany
    {
        return $this->hasMany(AvailabilityException::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
