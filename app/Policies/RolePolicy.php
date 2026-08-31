<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;

/**
 * The V1 role catalogue is fixed (docs/PRD.md §8) and seeded, not authored in
 * the app — so there's no create/update/delete here. Reading it is a
 * settings-level concern: it exposes the full permission map, which only an
 * admin needs to see.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::SettingsManage);
    }

    public function view(User $user): bool
    {
        return $user->hasPermission(PermissionName::SettingsManage);
    }
}
