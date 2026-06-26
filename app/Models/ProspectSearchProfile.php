<?php

namespace App\Models;

use App\Enums\ProspectDataSource;
use App\Enums\ProspectSearchTrigger;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProspectSearchProfile extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'industries',
        'ai_instructions',
        'data_source',
        'postal_code',
        'radius_km',
        'max_results_per_run',
        'exclude_existing_customers',
        'is_active',
        'schedule_enabled',
        'schedule_cron',
        'last_run_at',
        'next_run_at',
    ];

    protected function casts(): array
    {
        return [
            'industries' => 'array',
            'data_source' => ProspectDataSource::class,
            'exclude_existing_customers' => 'boolean',
            'is_active' => 'boolean',
            'schedule_enabled' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ProspectSearchRun::class);
    }

    public function effectiveMaxResults(?Plan $plan = null): int
    {
        $plan ??= $this->company?->effectivePlan();
        $planCap = $plan?->max_prospect_results_per_run;

        if ($planCap !== null) {
            return min($this->max_results_per_run, $planCap);
        }

        return $this->max_results_per_run;
    }
}
