<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price_cents',
        'currency',
        'included_staff',
        'included_customers',
        'extra_staff_price_cents',
        'extra_customer_price_cents',
        'stripe_product_id',
        'stripe_base_price_id',
        'stripe_staff_price_id',
        'stripe_customer_price_id',
        'is_active',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'included_staff' => 'integer',
            'included_customers' => 'integer',
            'extra_staff_price_cents' => 'integer',
            'extra_customer_price_cents' => 'integer',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    public static function defaultPlan(): ?self
    {
        return static::query()->where('is_default', true)->first();
    }

    public function hasUnlimitedStaff(): bool
    {
        return $this->included_staff === null;
    }

    public function hasUnlimitedCustomers(): bool
    {
        return $this->included_customers === null;
    }
}
