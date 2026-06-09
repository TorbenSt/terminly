<?php

namespace App\Models;

use Database\Factories\StaffAvailabilityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffAvailability extends Model
{
    /** @use HasFactory<StaffAvailabilityFactory> */
    use HasFactory;

    protected $fillable = [
        'staff_member_id',
        'day_of_week',
        'start_time',
        'end_time',
        'break_start_time',
        'break_end_time',
    ];

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }
}
