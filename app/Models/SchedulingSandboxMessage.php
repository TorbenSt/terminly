<?php

namespace App\Models;

use App\Enums\SchedulingSandboxMessageType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchedulingSandboxMessage extends Model
{
    protected $fillable = [
        'scheduling_sandbox_run_id',
        'appointment_proposal_id',
        'type',
        'subject',
        'body_html',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'type' => SchedulingSandboxMessageType::class,
            'meta' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(SchedulingSandboxRun::class, 'scheduling_sandbox_run_id');
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(AppointmentProposal::class, 'appointment_proposal_id');
    }
}
