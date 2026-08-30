<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\PayrollEntry;
use App\Models\User;
use App\Services\ScopeResolver;

/**
 * docs/PRD.md §69/§70 — an employee may always see their own payroll entry
 * (that's what the §70 confirmation screen is); everyone else needs
 * `payroll.view` resolved to the entry's employee. Manual adjustments
 * (§68) need `payroll.adjust`.
 */
class PayrollEntryPolicy
{
    public function __construct(private readonly ScopeResolver $scopeResolver) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::PayrollView)
            || $user->hasPermission(PermissionName::PayslipViewSelf);
    }

    public function view(User $user, PayrollEntry $entry): bool
    {
        if ($entry->employee_id === $user->employee?->id) {
            return true;
        }

        if (! $user->hasPermission(PermissionName::PayrollView)) {
            return false;
        }

        $allowedIds = $this->scopeResolver->employeeIdsFor($user, PermissionName::PayrollView);

        return $allowedIds === null || in_array($entry->employee_id, $allowedIds, true);
    }

    public function adjust(User $user, PayrollEntry $entry): bool
    {
        return $user->hasPermission(PermissionName::PayrollAdjust);
    }

    /**
     * §70 — confirming or disputing a payslip is the employee's own act,
     * on their own entry.
     */
    public function respond(User $user, PayrollEntry $entry): bool
    {
        return $entry->employee_id === $user->employee?->id;
    }

    public function viewPayslip(User $user, PayrollEntry $entry): bool
    {
        return $this->view($user, $entry);
    }
}
