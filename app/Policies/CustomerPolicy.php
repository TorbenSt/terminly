<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isCompanyAdmin() || $user->isStaff();
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->company_id === $customer->company_id;
    }

    public function create(User $user): bool
    {
        return $user->isCompanyAdmin();
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->isCompanyAdmin() && $user->company_id === $customer->company_id;
    }

    public function claimPrimaryStaff(User $user, Customer $customer): bool
    {
        return $user->isStaff()
            && $user->company_id === $customer->company_id;
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->isCompanyAdmin() && $user->company_id === $customer->company_id;
    }
}
