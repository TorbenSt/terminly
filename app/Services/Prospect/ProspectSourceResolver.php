<?php

namespace App\Services\Prospect;

use App\Contracts\ProspectSource;
use App\Enums\ProspectDataSource;

class ProspectSourceResolver
{
    public function __construct(
        protected GooglePlacesProspectSource $googlePlaces,
        protected ApifyProspectSource $apify,
    ) {}

    public function resolve(ProspectDataSource $source): ProspectSource
    {
        return match ($source) {
            ProspectDataSource::Apify => $this->apify,
            ProspectDataSource::GooglePlaces => $this->googlePlaces,
        };
    }

    /**
     * @return array<string, bool>
     */
    public function availability(): array
    {
        return [
            ProspectDataSource::GooglePlaces->value => $this->googlePlaces->isConfigured(),
            ProspectDataSource::Apify->value => $this->apify->isConfigured(),
        ];
    }
}
