<?php

namespace App\Services\Billing;

use App\Models\Company;

class PlanLimitService
{
    /**
     * Effektives Mitarbeiter-Limit: Override der Firma (>= 0), -1/null-Semantik:
     * Override -1 = unendlich, Override null = Plan-Wert, Plan-Wert null = unendlich.
     *
     * @return int|null null = unendlich
     */
    public function staffLimit(Company $company): ?int
    {
        if ($company->staff_limit_override !== null) {
            return $company->staff_limit_override < 0 ? null : $company->staff_limit_override;
        }

        return $company->effectivePlan()?->included_staff;
    }

    /**
     * @return int|null null = unendlich
     */
    public function customerLimit(Company $company): ?int
    {
        if ($company->customer_limit_override !== null) {
            return $company->customer_limit_override < 0 ? null : $company->customer_limit_override;
        }

        return $company->effectivePlan()?->included_customers;
    }

    public function staffUsage(Company $company): int
    {
        return $company->staffMembers()->where('is_active', true)->count();
    }

    public function customerUsage(Company $company): int
    {
        return $company->customers()->where('is_active', true)->count();
    }

    public function staffOverage(Company $company): int
    {
        $limit = $this->staffLimit($company);

        return $limit === null ? 0 : max(0, $this->staffUsage($company) - $limit);
    }

    public function customerOverage(Company $company): int
    {
        $limit = $this->customerLimit($company);

        return $limit === null ? 0 : max(0, $this->customerUsage($company) - $limit);
    }

    /**
     * Zusammenfassung für UI: Limits, Nutzung, Überschreitung und Zusatzkosten in Cents.
     *
     * @return array{
     *     staff: array{limit: int|null, usage: int, overage: int, extra_price_cents: int},
     *     customers: array{limit: int|null, usage: int, overage: int, extra_price_cents: int},
     *     extra_total_cents: int
     * }
     */
    public function summary(Company $company): array
    {
        $plan = $company->effectivePlan();

        $staffOverage = $this->staffOverage($company);
        $customerOverage = $this->customerOverage($company);

        $staffExtraPrice = $plan?->extra_staff_price_cents ?? 0;
        $customerExtraPrice = $plan?->extra_customer_price_cents ?? 0;

        return [
            'staff' => [
                'limit' => $this->staffLimit($company),
                'usage' => $this->staffUsage($company),
                'overage' => $staffOverage,
                'extra_price_cents' => $staffExtraPrice,
            ],
            'customers' => [
                'limit' => $this->customerLimit($company),
                'usage' => $this->customerUsage($company),
                'overage' => $customerOverage,
                'extra_price_cents' => $customerExtraPrice,
            ],
            'extra_total_cents' => $staffOverage * $staffExtraPrice + $customerOverage * $customerExtraPrice,
        ];
    }
}
