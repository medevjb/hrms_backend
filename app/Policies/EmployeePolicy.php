<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Employee;
use App\Models\User;
use App\Services\ScopeResolver;

class EmployeePolicy
{
    public function __construct(private readonly ScopeResolver $scopeResolver) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::EmployeeView);
    }

    public function view(User $user, Employee $employee): bool
    {
        return $this->canForScope($user, $employee, PermissionName::EmployeeView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionName::EmployeeCreate);
    }

    public function update(User $user, Employee $employee): bool
    {
        return $this->canForScope($user, $employee, PermissionName::EmployeeUpdate);
    }

    public function updateStatus(User $user, Employee $employee): bool
    {
        return $this->canForScope($user, $employee, PermissionName::EmployeeUpdate);
    }

    /**
     * True only if the user holds the permission AND the employee falls
     * inside their resolved scope. A caller with the permission but who
     * can't see this particular employee should get exactly what a caller
     * with no permission at all gets — 404, not 403 (docs/PRD.md §139.2).
     */
    private function canForScope(User $user, Employee $employee, PermissionName $permission): bool
    {
        if (! $user->hasPermission($permission)) {
            return false;
        }

        $allowedIds = $this->scopeResolver->employeeIdsFor($user, $permission);

        return $allowedIds === null || in_array($employee->id, $allowedIds, true);
    }
}
