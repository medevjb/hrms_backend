<?php

namespace App\Enums;

/**
 * docs/PRD.md §146 — an arrear is PENDING until the next payroll run for
 * its employee claims it, adds it as its own payslip line, and marks it
 * APPLIED. CANCELLED covers an arrear voided before it was ever paid.
 */
enum PayrollArrearStatus: string
{
    case Pending = 'PENDING';
    case Applied = 'APPLIED';
    case Cancelled = 'CANCELLED';
}
