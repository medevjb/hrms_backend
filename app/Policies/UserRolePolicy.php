<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;
use App\Models\UserRole;

/**
 * Governs who can grant/revoke role assignments. There's no "update" here —
 * a grant is revoked and recreated, never edited in place, so its scope
 * can't silently drift without a new, auditable grant.
 */
class UserRolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::SettingsManage);
    }

    public function view(User $user, UserRole $userRole): bool
    {
        return $user->hasPermission(PermissionName::SettingsManage);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionName::SettingsManage);
    }

    public function delete(User $user, UserRole $userRole): bool
    {
        return $user->hasPermission(PermissionName::SettingsManage);
    }
}
