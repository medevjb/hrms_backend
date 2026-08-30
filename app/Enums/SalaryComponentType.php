<?php

namespace App\Enums;

/**
 * docs/PRD.md §59 — a salary component is either the base pay or an
 * allowance on top of it. Both are earnings; the split matters only so the
 * payslip (§71) can itemise "Basic Salary" apart from the allowance lines.
 */
enum SalaryComponentType: string
{
    case Basic = 'BASIC';
    case Allowance = 'ALLOWANCE';
}
