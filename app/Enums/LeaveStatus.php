<?php

namespace App\Enums;

/** docs/PRD.md §39 — the complete V1 leave request status set. */
enum LeaveStatus: string
{
    case Draft = 'DRAFT';
    case Submitted = 'SUBMITTED';
    case TeamLeaderApproved = 'TEAM_LEADER_APPROVED';
    case OperationManagerApproved = 'OPERATION_MANAGER_APPROVED';
    case HrApproved = 'HR_APPROVED';
    case Rejected = 'REJECTED';
    case Cancelled = 'CANCELLED';
}
