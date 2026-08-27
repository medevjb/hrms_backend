<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;

/**
 * V1's department.manage grant is only ever seeded at ALL_EMPLOYEES scope
 * (Admin, Head of HR — see RolePermissionSeeder), so there's no per-record
 * scope narrowing to do here, unlike EmployeePolicy.
 */
class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::DepartmentView);
    }

    public function view(User $user): bool
    {
        return $user->hasPermission(PermissionName::DepartmentView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionName::DepartmentManage);
    }

    public function update(User $user): bool
    {
        return $user->hasPermission(PermissionName::DepartmentManage);
    }
}
