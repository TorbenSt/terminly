<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Database\Factories\RecurringServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringService extends Model
{
    /** @use HasFactory<RecurringServiceFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'customer_id',
        'service_type_id',
        'last_completed_at',
        'next_due_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'last_completed_at' => 'datetime',
            'next_due_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
