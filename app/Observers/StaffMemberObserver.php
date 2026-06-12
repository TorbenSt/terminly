<?php

namespace App\Observers;

use App\Jobs\SyncCompanyUsageJob;
use App\Models\StaffMember;

class StaffMemberObserver
{
    public function created(StaffMember $staffMember): void
    {
        $this->dispatchSync($staffMember);
    }

    public function updated(StaffMember $staffMember): void
    {
        if ($staffMember->wasChanged('is_active')) {
            $this->dispatchSync($staffMember);
        }
    }

    public function deleted(StaffMember $staffMember): void
    {
        $this->dispatchSync($staffMember);
    }

    protected function dispatchSync(StaffMember $staffMember): void
    {
        if ($staffMember->company_id) {
            SyncCompanyUsageJob::dispatch($staffMember->company_id);
        }
    }
}
