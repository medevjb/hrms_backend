<?php

namespace App\Enums;

/**
 * docs/PRD.md §61 — a late-penalty tier either just warns ("3 Late Days:
 * Warning Only") or deducts ("5 Late Days: 0.5 Day Salary Deduction").
 */
enum LatePenaltyOutcome: string
{
    case Warning = 'WARNING';
    case Deduction = 'DEDUCTION';
}
