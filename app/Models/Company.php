<?php

namespace App\Models;

use App\Enums\StaffCustomerBinding;
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
        'staff_customer_binding',
        'is_active',
        'is_sandbox',
        'sandbox_source_company_id',
        'sandbox_snapshot_at',
        'plan_id',
        'billing_exempt',
        'trial_ends_at',
        'staff_limit_override',
        'customer_limit_override',
        'prospect_search_override',
        'grok_feedback_collection_id',
    ];

    protected function casts(): array
    {
        return [
            'staff_customer_binding' => StaffCustomerBinding::class,
            'is_active' => 'boolean',
            'is_sandbox' => 'boolean',
            'sandbox_snapshot_at' => 'datetime',
            'billing_exempt' => 'boolean',
            'trial_ends_at' => 'datetime',
            'staff_limit_override' => 'integer',
            'customer_limit_override' => 'integer',
            'prospect_search_override' => 'boolean',
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

    public function prospectSearchProfiles(): HasMany
    {
        return $this->hasMany(ProspectSearchProfile::class);
    }

    public function customerProspects(): HasMany
    {
        return $this->hasMany(CustomerProspect::class);
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

    public function hasProspectSearchAddon(): bool
    {
        $subscription = $this->subscription('default');

        if (! $subscription || ! $subscription->valid()) {
            return false;
        }

        $priceId = BillingSetting::prospectSearchStripePriceId();

        if (! $priceId) {
            return false;
        }

        return $subscription->items()->where('stripe_price', $priceId)->exists();
    }

    /**
     * Kundensuche-Modul: Demo-befreit, Plan-Feature, Add-on oder Super-Admin-Override.
     */
    public function hasProspectSearchAccess(): bool
    {
        if ($this->billing_exempt) {
            return true;
        }

        if ($this->prospect_search_override === true) {
            return true;
        }

        if ($this->prospect_search_override === false) {
            return false;
        }

        if ($this->effectivePlan()?->includes_prospect_search) {
            return true;
        }

        return $this->hasProspectSearchAddon();
    }

    public function sandboxSourceCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'sandbox_source_company_id');
    }

    public function isSandbox(): bool
    {
        return (bool) $this->is_sandbox;
    }
}
