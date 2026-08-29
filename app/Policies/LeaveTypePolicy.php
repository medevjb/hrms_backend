<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;

/**
 * §35–§36 — every employee needs to see the type catalogue to file a
 * request; only leave.policy.manage may change it.
 */
class LeaveTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::LeaveRequest)
            || $user->hasPermission(PermissionName::LeavePolicyManage);
    }

    public function manage(User $user): bool
    {
        return $user->hasPermission(PermissionName::LeavePolicyManage);
    }
}
