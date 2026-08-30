<?php

namespace App\Enums;

/**
 * docs/PRD.md §66 — every payroll_entry_line is either an earning (adds to
 * gross) or a deduction (subtracts). Amounts are always stored positive;
 * this is what decides the sign in the net calculation.
 */
enum PayrollLineCategory: string
{
    case Earning = 'EARNING';
    case Deduction = 'DEDUCTION';
}
