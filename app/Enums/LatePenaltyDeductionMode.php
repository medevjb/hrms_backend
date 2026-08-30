<?php

namespace App\Enums;

/**
 * docs/PRD.md §61 — a deduction tier subtracts either a fraction of a day's
 * salary ("0.5 Day Salary Deduction") or a flat cash amount ("Each
 * Qualified Late: Fixed Deduction").
 */
enum LatePenaltyDeductionMode: string
{
    case DayFraction = 'DAY_FRACTION';
    case FixedAmount = 'FIXED_AMOUNT';
}
