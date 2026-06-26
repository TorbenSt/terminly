<?php

namespace App\Services\Prospect;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerProspect;
use Illuminate\Support\Str;

class ProspectDeduplicationService
{
    public function isDuplicate(Company $company, array $candidate, bool $excludeExistingCustomers = true): bool
    {
        if (! empty($candidate['google_place_id'])) {
            $existsAsProspect = CustomerProspect::query()
                ->where('company_id', $company->id)
                ->where('google_place_id', $candidate['google_place_id'])
                ->exists();

            if ($existsAsProspect) {
                return true;
            }

            if ($excludeExistingCustomers) {
                $existsAsCustomer = Customer::query()
                    ->where('company_id', $company->id)
                    ->where('google_place_id', $candidate['google_place_id'])
                    ->exists();

                if ($existsAsCustomer) {
                    return true;
                }
            }
        }

        if (! $excludeExistingCustomers) {
            return false;
        }

        $normalizedName = $this->normalizeName($candidate['company_name'] ?? '');

        if ($normalizedName === '') {
            return false;
        }

        $customers = Customer::query()
            ->where('company_id', $company->id)
            ->get(['id', 'name', 'email', 'phone', 'postal_code', 'city', 'address']);

        foreach ($customers as $customer) {
            if ($this->normalizeName($customer->name) === $normalizedName) {
                return true;
            }

            if ($this->samePostalAndCity($customer, $candidate) && $this->namesSimilar($customer->name, $candidate['company_name'])) {
                return true;
            }

            if ($this->phonesMatch($customer->phone, $candidate['phone'] ?? null)) {
                return true;
            }

            if ($this->emailsMatch($customer->email, $candidate['email'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    protected function normalizeName(string $name): string
    {
        $name = Str::lower($name);
        $name = preg_replace('/\b(gmbh|ag|kg|ug|e\.k\.|ohg|co\.|inc|ltd)\b/u', '', $name) ?? $name;

        return preg_replace('/[^a-z0-9äöüß]/u', '', $name) ?? '';
    }

    protected function namesSimilar(string $a, string $b): bool
    {
        $na = $this->normalizeName($a);
        $nb = $this->normalizeName($b);

        if ($na === '' || $nb === '') {
            return false;
        }

        similar_text($na, $nb, $percent);

        return $percent >= 85;
    }

    protected function samePostalAndCity(Customer $customer, array $candidate): bool
    {
        $postal = $candidate['postal_code'] ?? null;
        $city = $candidate['city'] ?? null;

        if (! $postal || ! $city || ! $customer->postal_code || ! $customer->city) {
            return false;
        }

        return Str::lower($customer->postal_code) === Str::lower($postal)
            && Str::lower($customer->city) === Str::lower($city);
    }

    protected function phonesMatch(?string $a, ?string $b): bool
    {
        if (! $a || ! $b) {
            return false;
        }

        return preg_replace('/\D+/', '', $a) === preg_replace('/\D+/', '', $b);
    }

    protected function emailsMatch(?string $a, ?string $b): bool
    {
        if (! $a || ! $b) {
            return false;
        }

        return Str::lower($a) === Str::lower($b);
    }
}
