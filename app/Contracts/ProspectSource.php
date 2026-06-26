<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

interface ProspectSource
{
    /**
     * @return Collection<int, array{
     *     google_place_id: string,
     *     company_name: string,
     *     address: string|null,
     *     postal_code: string|null,
     *     city: string|null,
     *     phone: string|null,
     *     email: string|null,
     *     latitude: float|null,
     *     longitude: float|null,
     *     industry: string|null,
     *     source_url: string|null,
     *     source: string
     * }>
     */
    public function search(string $postalCode, int $radiusKm, array $industries, int $maxResults): Collection;

    public function isConfigured(): bool;
}
