<?php

namespace App\Enums;

/**
 * docs/PRD.md §50 — the overtime approval chain, always in this order. It
 * has none of leave's role-based branching (§41): every overtime record,
 * whoever it belongs to, walks TEAM_LEADER → OPERATION_MANAGER → HR.
 * Admin/Head HR "exceptional authority" (§50) is an escalation on top,
 * not a different chain — see OvertimeService::approve().
 */
enum OvertimeApprovalStage: string
{
    case TeamLeader = 'TEAM_LEADER';
    case OperationManager = 'OPERATION_MANAGER';
    case Hr = 'HR';
}
