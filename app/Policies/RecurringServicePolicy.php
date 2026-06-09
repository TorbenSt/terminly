<?php

namespace App\Policies;

use App\Models\RecurringService;
use App\Models\User;

class RecurringServicePolicy
{
    public function create(User $user): bool
    {
        return $user->isCompanyAdmin() || $user->isStaff();
    }

    public function update(User $user, RecurringService $recurringService): bool
    {
        return ($user->isCompanyAdmin() || $user->isStaff())
            && $user->company_id === $recurringService->company_id;
    }

    public function delete(User $user, RecurringService $recurringService): bool
    {
        return ($user->isCompanyAdmin() || $user->isStaff())
            && $user->company_id === $recurringService->company_id;
    }
}
