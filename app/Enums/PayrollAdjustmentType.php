<?php

namespace App\Enums;

/**
 * docs/PRD.md §68 — the manual moves an authorised HR user can make on a
 * draft payroll entry. Each one produces a payroll_entry_line and records
 * reason / previous value / new value / actor / timestamp.
 */
enum PayrollAdjustmentType: string
{
    case AddEarning = 'ADD_EARNING';
    case AddDeduction = 'ADD_DEDUCTION';
    case Bonus = 'BONUS';
    case WaivePenalty = 'WAIVE_PENALTY';
    case OvertimeAdjustment = 'OVERTIME_ADJUSTMENT';

    public function lineType(): PayrollLineType
    {
        return match ($this) {
            self::AddEarning, self::OvertimeAdjustment => PayrollLineType::ManualEarning,
            self::Bonus => PayrollLineType::Bonus,
            self::AddDeduction => PayrollLineType::ManualDeduction,
            self::WaivePenalty => PayrollLineType::ManualEarning, // a credit that offsets a penalty line
        };
    }
}
