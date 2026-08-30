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

    /**
     * §69 — moving a period to review and releasing it to employees is
     * preparation work; finalising, marking paid, and locking need
     * `payroll.finalize` (a §92.5 mandatory-2FA permission).
     */
    public function advance(User $user, PayrollPeriod $period): bool
    {
        return $user->hasPermission(PermissionName::PayrollPrepare);
    }

    public function finalize(User $user, PayrollPeriod $period): bool
    {
        return $user->hasPermission(PermissionName::PayrollFinalize);
    }

    public function createArrear(User $user, PayrollPeriod $period): bool
    {
        return $user->hasPermission(PermissionName::PayrollAdjust);
    }
}
