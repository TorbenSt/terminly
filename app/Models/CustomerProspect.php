<?php

namespace App\Models;

use App\Enums\ProspectStatus;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CustomerProspect extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id',
        'prospect_search_run_id',
        'status',
        'company_name',
        'contact_name',
        'email',
        'phone',
        'address',
        'postal_code',
        'city',
        'latitude',
        'longitude',
        'industry',
        'match_score',
        'match_reason',
        'source',
        'source_url',
        'google_place_id',
        'last_contacted_at',
        'contact_count',
        'converted_customer_id',
        'opt_out_token',
        'notes',
        'discovered_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProspectStatus::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'last_contacted_at' => 'datetime',
            'discovered_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CustomerProspect $prospect) {
            if (! $prospect->opt_out_token) {
                $prospect->opt_out_token = Str::random(48);
            }
            if (! $prospect->discovered_at) {
                $prospect->discovered_at = now();
            }
        });
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ProspectSearchRun::class, 'prospect_search_run_id');
    }

    public function convertedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'converted_customer_id');
    }

    public function outreachEmails(): HasMany
    {
        return $this->hasMany(ProspectOutreachEmail::class);
    }

    public function canReceiveOutreach(): bool
    {
        return filled($this->email)
            && ! in_array($this->status, [ProspectStatus::OptedOut, ProspectStatus::Converted, ProspectStatus::Discarded], true);
    }
}
