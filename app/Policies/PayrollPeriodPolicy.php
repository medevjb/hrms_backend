<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\PayrollPeriod;
use App\Models\User;

/**
 * docs/PRD.md §69 — viewing payroll periods needs `payroll.view`; creating
 * one and running the draft calculation needs `payroll.prepare`.
 */
class PayrollPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::PayrollView);
    }

    public function view(User $user, PayrollPeriod $period): bool
    {
        return $user->hasPermission(PermissionName::PayrollView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionName::PayrollPrepare);
    }

    public function generate(User $user, PayrollPeriod $period): bool
    {
        return $user->hasPermission(PermissionName::PayrollPrepare);
    }
}
