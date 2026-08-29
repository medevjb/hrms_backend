<?php

namespace App\Policies;

use App\Enums\OvertimeApprovalStage;
use App\Enums\PermissionName;
use App\Models\OvertimeRecord;
use App\Models\User;
use App\Services\ScopeResolver;

/**
 * §50 — the TEAM_LEADER/OPERATION_MANAGER stages are gated to the one
 * person the org chart names for this record's employee; the HR stage is
 * role-gated. Admin/Head of HR may act at any stage ("exceptional
 * authority", §50) — and when they do, OvertimeService::approve()
 * collapses the rest of the chain. Mirrors LeaveRequestPolicy.
 */
class OvertimeRecordPolicy
{
    public function __construct(private readonly ScopeResolver $scopeResolver) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::OvertimeView)
            || $user->hasPermission(PermissionName::OvertimeReview)
            || $user->hasPermission(PermissionName::OvertimeApprove);
    }

    public function view(User $user, OvertimeRecord $record): bool
    {
        if ($record->employee_id === $user->employee?->id) {
            return true;
        }

        return $this->canForScope($user, $record, PermissionName::OvertimeReview);
    }

    public function approve(User $user, OvertimeRecord $record): bool
    {
        if ($record->current_stage === null || ! $user->hasPermission(PermissionName::OvertimeApprove)) {
            return false;
        }

        if ($this->isSenior($user)) {
            return true;
        }

        return match ($record->current_stage) {
            OvertimeApprovalStage::TeamLeader => $user->id === $record->employee->teamLeader()?->user_id,
            OvertimeApprovalStage::OperationManager => $user->id === $record->employee->operationManager()?->user_id,
            OvertimeApprovalStage::Hr => $user->hasRole('HR'),
        };
    }

    public function adjust(User $user, OvertimeRecord $record): bool
    {
        return $user->hasPermission(PermissionName::OvertimeAdjust);
    }

    private function isSenior(User $user): bool
    {
        return $user->hasRole('Head of HR') || $user->hasRole('Admin');
    }

    private function canForScope(User $user, OvertimeRecord $record, PermissionName $permission): bool
    {
        if (! $user->hasPermission($permission)) {
            return false;
        }

        $allowedIds = $this->scopeResolver->employeeIdsFor($user, $permission);

        return $allowedIds === null || in_array($record->employee_id, $allowedIds, true);
    }
}
