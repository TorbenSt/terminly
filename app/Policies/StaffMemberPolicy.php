<?php

namespace App\Policies;

use App\Models\StaffMember;
use App\Models\User;

class StaffMemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isCompanyAdmin() || $user->isStaff();
    }

    public function view(User $user, StaffMember $staffMember): bool
    {
        return $user->company_id === $staffMember->company_id;
    }

    public function create(User $user): bool
    {
        return $user->isCompanyAdmin();
    }

    public function update(User $user, StaffMember $staffMember): bool
    {
        return $user->company_id === $staffMember->company_id
            && ($user->isCompanyAdmin() || $staffMember->user_id === $user->id);
    }
}
