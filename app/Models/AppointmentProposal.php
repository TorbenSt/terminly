<?php

namespace App\Models;

use App\Enums\NegotiationStatus;
use Database\Factories\AppointmentProposalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AppointmentProposal extends Model
{
    /** @use HasFactory<AppointmentProposalFactory> */
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'round',
        'option_1_at',
        'option_2_at',
        'option_3_at',
        'recommended_option',
        'staff_member_id',
        'selected_option',
        'token',
        'email_sent_at',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'option_1_at' => 'datetime',
            'option_2_at' => 'datetime',
            'option_3_at' => 'datetime',
            'email_sent_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AppointmentProposal $proposal) {
            if (! $proposal->token) {
                $proposal->token = Str::random(48);
            }
        });
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    /**
     * @return array<int, Carbon>
     */
    public function options(): array
    {
        return [
            1 => $this->option_1_at,
            2 => $this->option_2_at,
            3 => $this->option_3_at,
        ];
    }

    public function negotiationFeedback(): ?string
    {
        if ($this->round <= 1) {
            return null;
        }

        $feedback = $this->appointment
            ?->negotiations()
            ->where('round', $this->round - 1)
            ->where('status', NegotiationStatus::Processed)
            ->value('customer_feedback');

        return filled($feedback) ? $feedback : null;
    }
}
