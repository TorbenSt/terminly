<?php

namespace App\Policies;

use App\Models\CustomerProspect;
use App\Models\ProspectSearchProfile;
use App\Models\User;

class ProspectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isCompanyAdmin();
    }

    public function view(User $user, CustomerProspect|ProspectSearchProfile $model): bool
    {
        return $user->isCompanyAdmin() && $user->company_id === $model->company_id;
    }

    public function create(User $user): bool
    {
        return $user->isCompanyAdmin();
    }

    public function update(User $user, CustomerProspect|ProspectSearchProfile $model): bool
    {
        return $user->isCompanyAdmin() && $user->company_id === $model->company_id;
    }

    public function delete(User $user, CustomerProspect|ProspectSearchProfile $model): bool
    {
        return $user->isCompanyAdmin() && $user->company_id === $model->company_id;
    }
}
