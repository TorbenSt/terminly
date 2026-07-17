<?php

namespace App\DTOs;

use App\Enums\DeadlinePhase;
use App\Enums\StaffCustomerBinding;

readonly class StaffBindingPreference
{
    public function __construct(
        public StaffCustomerBinding $mode,
        public ?int $primaryStaffId = null,
        public ?int $backupStaffId = null,
        public DeadlinePhase $phase = DeadlinePhase::Green,
    ) {}

    public function hasPreferredStaff(): bool
    {
        return $this->primaryStaffId !== null || $this->backupStaffId !== null;
    }

    /**
     * @return array{mode: string, primary_staff_id: ?int, backup_staff_id: ?int, deadline_phase: string}
     */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode->value,
            'primary_staff_id' => $this->primaryStaffId,
            'backup_staff_id' => $this->backupStaffId,
            'deadline_phase' => $this->phase->value,
        ];
    }
}
