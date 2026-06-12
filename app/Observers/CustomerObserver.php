<?php

namespace App\Observers;

use App\Jobs\SyncCompanyUsageJob;
use App\Models\Customer;

class CustomerObserver
{
    public function created(Customer $customer): void
    {
        $this->dispatchSync($customer);
    }

    public function updated(Customer $customer): void
    {
        if ($customer->wasChanged('is_active')) {
            $this->dispatchSync($customer);
        }
    }

    public function deleted(Customer $customer): void
    {
        $this->dispatchSync($customer);
    }

    protected function dispatchSync(Customer $customer): void
    {
        if ($customer->company_id) {
            SyncCompanyUsageJob::dispatch($customer->company_id);
        }
    }
}
