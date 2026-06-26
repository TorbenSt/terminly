<?php

namespace App\Enums;

enum ProspectDataSource: string
{
    case GooglePlaces = 'google_places';
    case Apify = 'apify';

    public function label(): string
    {
        return match ($this) {
            self::GooglePlaces => 'Google Places API',
            self::Apify => 'Apify (Google Maps)',
        };
    }
}
