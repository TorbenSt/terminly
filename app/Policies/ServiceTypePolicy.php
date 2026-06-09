<?php

namespace App\Policies;

use App\Models\ServiceType;
use App\Models\User;

class ServiceTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isCompanyAdmin() || $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isCompanyAdmin();
    }

    public function update(User $user, ServiceType $serviceType): bool
    {
        return $user->isCompanyAdmin() && $user->company_id === $serviceType->company_id;
    }

    public function delete(User $user, ServiceType $serviceType): bool
    {
        return $user->isCompanyAdmin() && $user->company_id === $serviceType->company_id;
    }
}
