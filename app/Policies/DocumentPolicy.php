<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Document;
use App\Models\Employee;
use App\Models\User;
use App\Services\ScopeResolver;

/**
 * docs/PRD.md §82 — an employee may always see and download their own
 * files; managing (upload / delete) and seeing others' needs
 * `document.view` / `document.manage` resolved to that employee's scope.
 */
class DocumentPolicy
{
    public function __construct(private readonly ScopeResolver $scopeResolver) {}

    public function viewForEmployee(User $user, Employee $employee): bool
    {
        return $employee->id === $user->employee?->id
            || $this->scoped($user, $employee, PermissionName::DocumentView);
    }

    public function view(User $user, Document $document): bool
    {
        return $document->employee_id === $user->employee?->id
            || $this->scoped($user, $document->employee, PermissionName::DocumentView);
    }

    public function manageForEmployee(User $user, Employee $employee): bool
    {
        return $this->scoped($user, $employee, PermissionName::DocumentManage);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->scoped($user, $document->employee, PermissionName::DocumentManage);
    }

    private function scoped(User $user, Employee $employee, PermissionName $permission): bool
    {
        if (! $user->hasPermission($permission)) {
            return false;
        }

        $allowed = $this->scopeResolver->employeeIdsFor($user, $permission);

        return $allowed === null || in_array($employee->id, $allowed, true);
    }
}
