<?php

namespace App\Models;

use App\Enums\SchedulingSandboxMode;
use App\Enums\SchedulingSandboxRunStatus;
use App\Enums\SchedulingSandboxScenario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchedulingSandboxRun extends Model
{
    protected $fillable = [
        'company_id',
        'created_by_user_id',
        'mode',
        'scenario',
        'source_company_id',
        'status',
        'use_grok_live',
        'snapshot_meta',
        'grok_debug',
        'validation_results',
    ];

    protected function casts(): array
    {
        return [
            'mode' => SchedulingSandboxMode::class,
            'scenario' => SchedulingSandboxScenario::class,
            'status' => SchedulingSandboxRunStatus::class,
            'use_grok_live' => 'boolean',
            'snapshot_meta' => 'array',
            'grok_debug' => 'array',
            'validation_results' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function sourceCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'source_company_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SchedulingSandboxMessage::class);
    }
}
