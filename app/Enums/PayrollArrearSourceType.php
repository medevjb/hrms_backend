<?php

namespace App\Enums;

/**
 * docs/PRD.md §146 — what produced an arrear. OVERTIME is the common case
 * (§72 — approved after the period finalised); ADJUSTMENT / CORRECTION
 * carry a manual or attendance-correction delta into the next period.
 */
enum PayrollArrearSourceType: string
{
    case Overtime = 'OVERTIME';
    case Adjustment = 'ADJUSTMENT';
    case Correction = 'CORRECTION';
}
