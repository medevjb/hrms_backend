<?php

namespace App\Enums;

/**
 * docs/PRD.md §66/§71 — the itemised line types a payslip shows. The first
 * five are earnings, the rest deductions; PayrollLineType::category() keeps
 * that mapping in one place.
 */
enum PayrollLineType: string
{
    case Basic = 'BASIC';
    case Allowance = 'ALLOWANCE';
    case Overtime = 'OVERTIME';
    case Bonus = 'BONUS';
    case ManualEarning = 'MANUAL_EARNING';

    case LatePenalty = 'LATE_PENALTY';
    case Absence = 'ABSENCE';
    case UnpaidLeave = 'UNPAID_LEAVE';
    case ManualDeduction = 'MANUAL_DEDUCTION';

    public function category(): PayrollLineCategory
    {
        return match ($this) {
            self::Basic, self::Allowance, self::Overtime, self::Bonus, self::ManualEarning => PayrollLineCategory::Earning,
            self::LatePenalty, self::Absence, self::UnpaidLeave, self::ManualDeduction => PayrollLineCategory::Deduction,
        };
    }
}
