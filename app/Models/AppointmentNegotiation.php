<?php

namespace App\Models;

use App\Enums\NegotiationStatus;
use Database\Factories\AppointmentNegotiationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AppointmentNegotiation extends Model
{
    /** @use HasFactory<AppointmentNegotiationFactory> */
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'round',
        'customer_feedback',
        'ai_summary',
        'status',
        'token',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => NegotiationStatus::class,
            'processed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AppointmentNegotiation $negotiation) {
            if (! $negotiation->token) {
                $negotiation->token = Str::random(48);
            }
        });
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
