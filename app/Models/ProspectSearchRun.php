<?php

namespace App\Models;

use App\Enums\ProspectSearchRunStatus;
use App\Enums\ProspectSearchTrigger;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProspectSearchRun extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'prospect_search_profile_id',
        'status',
        'trigger',
        'data_source',
        'requested_max_results',
        'candidates_found',
        'duplicates_skipped',
        'prospects_saved',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProspectSearchRunStatus::class,
            'trigger' => ProspectSearchTrigger::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ProspectSearchProfile::class, 'prospect_search_profile_id');
    }

    public function prospects(): HasMany
    {
        return $this->hasMany(CustomerProspect::class);
    }
}
