<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;

class ShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::ShiftView);
    }

    public function view(User $user): bool
    {
        return $user->hasPermission(PermissionName::ShiftView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionName::ShiftManage);
    }

    public function update(User $user): bool
    {
        return $user->hasPermission(PermissionName::ShiftManage);
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission(PermissionName::ShiftManage);
    }

    /**
     * docs/PRD.md §23 — a temporary shift change for one day is a distinct
     * privilege from managing the shift catalogue itself.
     */
    public function override(User $user): bool
    {
        return $user->hasPermission(PermissionName::ShiftOverride);
    }
}
