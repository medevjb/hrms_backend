<?php

namespace App\Enums;

/**
 * docs/PRD.md §138 — which half of the work day a half-day leave request
 * covers, expressed relative to the shift rather than literal AM/PM so it
 * still makes sense against an overnight shift.
 */
enum HalfDayPeriod: string
{
    case FirstHalf = 'FIRST_HALF';
    case SecondHalf = 'SECOND_HALF';
}
