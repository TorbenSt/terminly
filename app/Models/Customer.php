<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'email',
        'phone',
        'address',
        'postal_code',
        'city',
        'latitude',
        'longitude',
        'notes',
        'google_place_id',
        'is_active',
        'primary_staff_member_id',
        'backup_staff_member_id',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_active' => 'boolean',
        ];
    }

    public function primaryStaffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class, 'primary_staff_member_id');
    }

    public function backupStaffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class, 'backup_staff_member_id');
    }

    public function recurringServices(): HasMany
    {
        return $this->hasMany(RecurringService::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function postalRegion(): string
    {
        return substr($this->postal_code, 0, 3);
    }
}
