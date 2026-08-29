<?php

namespace App\Enums;

enum LeaveApprovalDecision: string
{
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
}
