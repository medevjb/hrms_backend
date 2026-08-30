<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\PayrollDispute;
use App\Models\User;

/**
 * docs/PRD.md §147 — investigating and resolving a payroll dispute needs
 * `payroll.dispute.resolve`. An employee can see the disputes on their own
 * entry through the entry itself, not this list.
 */
class PayrollDisputePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::PayrollDisputeResolve);
    }

    public function resolve(User $user, PayrollDispute $dispute): bool
    {
        return $user->hasPermission(PermissionName::PayrollDisputeResolve);
    }
}
