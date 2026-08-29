<?php

namespace App\Policies;

use App\Enums\LeaveApprovalStage;
use App\Enums\PermissionName;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\ScopeResolver;

/**
 * §38–§41 — unlike AttendanceRecordPolicy, "can view/act on this record" is
 * not purely scope-resolved: the TEAM_LEADER/OPERATION_MANAGER stages are
 * gated to the one specific person the org chart names, while the
 * HR/HEAD_HR/ADMIN tiers are role-gated and escalate (a more senior role
 * may act at a junior tier, mirroring real sign-off authority).
 */
class LeaveRequestPolicy
{
    public function __construct(private readonly ScopeResolver $scopeResolver) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::LeaveRequest)
            || $user->hasPermission(PermissionName::LeaveReview)
            || $user->hasPermission(PermissionName::LeaveApprove);
    }

    public function view(User $user, LeaveRequest $request): bool
    {
        if ($request->employee_id === $user->employee?->id) {
            return true;
        }

        return $this->canForScope($user, $request, PermissionName::LeaveReview);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionName::LeaveRequest);
    }

    public function cancel(User $user, LeaveRequest $request): bool
    {
        if ($request->employee_id === $user->employee?->id) {
            return true;
        }

        return $user->hasPermission(PermissionName::LeaveOverride) || $this->isSeniorHr($user);
    }

    public function approve(User $user, LeaveRequest $request): bool
    {
        if ($request->current_stage === null || ! $user->hasPermission(PermissionName::LeaveApprove)) {
            return false;
        }

        return match ($request->current_stage) {
            LeaveApprovalStage::TeamLeader => $user->id === $request->employee->teamLeader()?->user_id,
            LeaveApprovalStage::OperationManager => $user->id === $request->employee->operationManager()?->user_id,
            LeaveApprovalStage::Hr => $user->hasRole('HR') || $this->isSeniorHr($user),
            LeaveApprovalStage::HeadHr => $user->hasRole('Head of HR') || $user->hasRole('Admin'),
            LeaveApprovalStage::Admin => $user->hasRole('Admin'),
        };
    }

    public function directApprove(User $user, LeaveRequest $request): bool
    {
        return $request->current_stage !== null
            && $user->hasPermission(PermissionName::LeaveOverride)
            && $this->isSeniorHr($user);
    }

    private function isSeniorHr(User $user): bool
    {
        return $user->hasRole('Head of HR') || $user->hasRole('Admin');
    }

    private function canForScope(User $user, LeaveRequest $request, PermissionName $permission): bool
    {
        if (! $user->hasPermission($permission)) {
            return false;
        }

        $allowedIds = $this->scopeResolver->employeeIdsFor($user, $permission);

        return $allowedIds === null || in_array($request->employee_id, $allowedIds, true);
    }
}
