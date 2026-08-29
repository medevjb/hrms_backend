<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\LeaveBalance;
use App\Models\User;
use App\Services\ScopeResolver;

class LeaveBalancePolicy
{
    public function __construct(private readonly ScopeResolver $scopeResolver) {}

    public function view(User $user, LeaveBalance $balance): bool
    {
        if ($balance->employee_id === $user->employee?->id) {
            return true;
        }

        return $this->canForScope($user, $balance, PermissionName::LeaveReview)
            || $this->canForScope($user, $balance, PermissionName::LeaveBalanceAdjust);
    }

    public function adjust(User $user, LeaveBalance $balance): bool
    {
        return $this->canForScope($user, $balance, PermissionName::LeaveBalanceAdjust);
    }

    private function canForScope(User $user, LeaveBalance $balance, PermissionName $permission): bool
    {
        if (! $user->hasPermission($permission)) {
            return false;
        }

        $allowedIds = $this->scopeResolver->employeeIdsFor($user, $permission);

        return $allowedIds === null || in_array($balance->employee_id, $allowedIds, true);
    }
}
