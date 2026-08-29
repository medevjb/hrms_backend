<?php

namespace App\Enums;

/**
 * docs/PRD.md §144 — every movement against a leave_balances row writes one
 * of these. The balance column is a cached sum; it must always be
 * reconstructible by replaying these rows in order.
 */
enum LeaveBalanceTransactionType: string
{
    case Accrual = 'ACCRUAL';
    case CarryForward = 'CARRY_FORWARD';
    case Expiry = 'EXPIRY';
    case Approval = 'APPROVAL';
    case Cancellation = 'CANCELLATION';
    case Adjustment = 'ADJUSTMENT';
}
