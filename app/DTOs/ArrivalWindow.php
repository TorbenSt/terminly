<?php

namespace App\DTOs;

use Carbon\Carbon;

readonly class ArrivalWindow
{
    public function __construct(
        public Carbon $start,
        public Carbon $end,
        public int $widthMinutes,
        public int $priorAppointmentsCount,
    ) {}
}
