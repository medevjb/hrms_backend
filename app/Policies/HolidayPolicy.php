<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;

class HolidayPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::HolidayView);
    }

    public function view(User $user): bool
    {
        return $user->hasPermission(PermissionName::HolidayView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionName::HolidayManage);
    }

    public function update(User $user): bool
    {
        return $user->hasPermission(PermissionName::HolidayManage);
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission(PermissionName::HolidayManage);
    }
}
