<?php

namespace App\DTOs;

use Illuminate\Support\Collection;

readonly class PostalCluster
{
    /**
     * @param  Collection<int, array<string, mixed>>  $jobs
     */
    public function __construct(
        public string $region,
        public Collection $jobs,
        public ?string $suggestedDate = null,
    ) {}

    public function toArray(): array
    {
        return [
            'region' => $this->region,
            'suggested_date' => $this->suggestedDate,
            'job_count' => $this->jobs->count(),
            'jobs' => $this->jobs->values()->all(),
        ];
    }
}
