<?php

namespace App\Enums;

/**
 * docs/PRD.md §38, §41 — every stage a leave request can require, in the
 * order §41 lists them. A request's own chain is a subset snapshotted onto
 * required_stages at submission, since which stages apply depends on the
 * requester's own role.
 */
enum LeaveApprovalStage: string
{
    case TeamLeader = 'TEAM_LEADER';
    case OperationManager = 'OPERATION_MANAGER';
    case Hr = 'HR';
    case HeadHr = 'HEAD_HR';
    case Admin = 'ADMIN';
}
