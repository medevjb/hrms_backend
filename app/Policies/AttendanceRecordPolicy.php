<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\AttendanceRecord;
use App\Models\User;
use App\Services\ScopeResolver;

class AttendanceRecordPolicy
{
    public function __construct(private readonly ScopeResolver $scopeResolver) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::AttendanceView);
    }

    public function view(User $user, AttendanceRecord $record): bool
    {
        return $this->canForScope($user, $record, PermissionName::AttendanceView);
    }

    /**
     * §32 manual correction — gated separately from attendance.view since
     * seeing attendance and rewriting it are different privileges.
     */
    public function correct(User $user, AttendanceRecord $record): bool
    {
        return $this->canForScope($user, $record, PermissionName::AttendanceCorrect);
    }

    /**
     * Mirrors EmployeePolicy::canForScope — a caller with the permission
     * but whose scope excludes this employee gets exactly what a caller
     * with no permission gets: 404, not 403 (docs/PRD.md §139.2).
     */
    private function canForScope(User $user, AttendanceRecord $record, PermissionName $permission): bool
    {
        if (! $user->hasPermission($permission)) {
            return false;
        }

        $allowedIds = $this->scopeResolver->employeeIdsFor($user, $permission);

        return $allowedIds === null || in_array($record->employee_id, $allowedIds, true);
    }
}
