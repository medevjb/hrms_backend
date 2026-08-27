<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;

class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::TeamView);
    }

    public function view(User $user): bool
    {
        return $user->hasPermission(PermissionName::TeamView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionName::TeamManage);
    }

    public function update(User $user): bool
    {
        return $user->hasPermission(PermissionName::TeamManage);
    }

    public function manageMembers(User $user): bool
    {
        return $user->hasPermission(PermissionName::TeamManage);
    }
}
