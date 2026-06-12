<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Billable;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use Billable, HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'email',
        'phone',
        'timezone',
        'is_active',
        'plan_id',
        'billing_exempt',
        'trial_ends_at',
        'staff_limit_override',
        'customer_limit_override',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'billing_exempt' => 'boolean',
            'trial_ends_at' => 'datetime',
            'staff_limit_override' => 'integer',
            'customer_limit_override' => 'integer',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function serviceTypes(): HasMany
    {
        return $this->hasMany(ServiceType::class);
    }

    public function staffMembers(): HasMany
    {
        return $this->hasMany(StaffMember::class);
    }

    public function recurringServices(): HasMany
    {
        return $this->hasMany(RecurringService::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Der für Limits maßgebliche Plan: gebuchter Plan, sonst Standard-Plan (z.B. während des Trials).
     */
    public function effectivePlan(): ?Plan
    {
        return $this->plan ?? Plan::defaultPlan();
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscribed('default');
    }

    /**
     * Voller (schreibender) Zugriff: Billing-befreit, im Testzeitraum oder mit aktivem Abo.
     */
    public function hasFullAccess(): bool
    {
        return $this->billing_exempt
            || $this->onGenericTrial()
            || $this->hasActiveSubscription();
    }
}
