<?php

namespace App\Enums;

/**
 * docs/PRD.md §51 — the complete V1 overtime status set. DETECTED is the
 * instant a candidate is found (§50); it moves to a PENDING_* stage the
 * same tick if it clears §46's minimum duration, or straight to REJECTED
 * if it doesn't (§107 open-question #7 — a sub-threshold day is surfaced,
 * not hidden). PAYROLL_PROCESSED is set by Phase 8/9 once the approved
 * days are carried into a finalised payroll run (§72).
 */
enum OvertimeStatus: string
{
    case Detected = 'DETECTED';
    case PendingTeamLeader = 'PENDING_TEAM_LEADER';
    case PendingOperationManager = 'PENDING_OPERATION_MANAGER';
    case PendingHr = 'PENDING_HR';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case PayrollProcessed = 'PAYROLL_PROCESSED';
}
