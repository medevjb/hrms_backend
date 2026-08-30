<?php

namespace App\Enums;

/**
 * docs/PRD.md §147 — a payroll dispute is OPEN from the moment the
 * employee raises it until HR records a resolution (with an explanation —
 * "a dispute resolved without an explanation is not resolved").
 */
enum PayrollDisputeStatus: string
{
    case Open = 'OPEN';
    case Resolved = 'RESOLVED';
}
