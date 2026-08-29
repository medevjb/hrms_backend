<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;

/**
 * One singleton row, four differently-gated views over it (docs/PRD.md
 * §139.6) — there is no separate "view" permission for settings, since
 * this screen is admin configuration, not information every employee
 * needs; holding the manage permission is what lets you see the screen
 * at all, so the same check gates both GET and PUT for each group.
 */
class OrganizationSettingsPolicy
{
    public function organization(User $user): bool
    {
        return $user->hasPermission(PermissionName::SettingsManage);
    }

    public function attendance(User $user): bool
    {
        return $user->hasPermission(PermissionName::AttendanceSettingsManage);
    }

    public function overtime(User $user): bool
    {
        return $user->hasPermission(PermissionName::OvertimePolicyManage);
    }

    public function payroll(User $user): bool
    {
        return $user->hasPermission(PermissionName::PayrollSettingsManage);
    }

    public function leave(User $user): bool
    {
        return $user->hasPermission(PermissionName::LeavePolicyManage);
    }
}
