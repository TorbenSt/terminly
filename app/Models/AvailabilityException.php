<?php

namespace App\Models;

use Database\Factories\AvailabilityExceptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilityException extends Model
{
    /** @use HasFactory<AvailabilityExceptionFactory> */
    use HasFactory;

    protected $fillable = [
        'staff_member_id',
        'date',
        'is_available',
        'start_time',
        'end_time',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_available' => 'boolean',
        ];
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }
}
