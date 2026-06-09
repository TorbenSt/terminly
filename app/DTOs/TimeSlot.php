<?php

namespace App\DTOs;

use Carbon\Carbon;

readonly class TimeSlot
{
    public function __construct(
        public Carbon $start,
        public Carbon $end,
        public int $staffMemberId,
    ) {}

    public function toArray(): array
    {
        return [
            'staff_id' => $this->staffMemberId,
            'start' => $this->start->toIso8601String(),
            'end' => $this->end->toIso8601String(),
            'duration_min' => $this->start->diffInMinutes($this->end),
        ];
    }
}
