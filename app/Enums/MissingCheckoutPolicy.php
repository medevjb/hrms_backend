<?php

namespace App\Enums;

/** docs/PRD.md §137 */
enum MissingCheckoutPolicy: string
{
    case LeaveOpen = 'LEAVE_OPEN';
    case AutoCloseAtShiftEnd = 'AUTO_CLOSE_AT_SHIFT_END';
}
