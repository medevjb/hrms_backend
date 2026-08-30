<?php

namespace App\Enums;

/**
 * docs/PRD.md §64 — a payroll period's lifecycle. Phase 8 uses UPCOMING →
 * OPEN → PROCESSING (draft calculated); REVIEW onward is Phase 9. Once a
 * period is FINALIZED / PAID / LOCKED it is immutable and corrections go
 * through payroll_arrears (§72, §146).
 */
enum PayrollPeriodStatus: string
{
    case Upcoming = 'UPCOMING';
    case Open = 'OPEN';
    case Processing = 'PROCESSING';
    case Review = 'REVIEW';
    case EmployeeConfirmation = 'EMPLOYEE_CONFIRMATION';
    case Finalized = 'FINALIZED';
    case Paid = 'PAID';
    case Locked = 'LOCKED';

    public function isClosed(): bool
    {
        return match ($this) {
            self::Finalized, self::Paid, self::Locked => true,
            default => false,
        };
    }

    public function allowsRecalculation(): bool
    {
        return match ($this) {
            self::Open, self::Processing, self::Review => true,
            default => false,
        };
    }
}
