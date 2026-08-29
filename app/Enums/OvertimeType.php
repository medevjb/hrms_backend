<?php

namespace App\Enums;

/**
 * docs/PRD.md §44/§45 — what made a day eligible for overtime. Weekend and
 * holiday overtime are configured and (for now) paid identically, but the
 * distinction is kept because §43 toggles them separately and a payslip
 * (§71) itemises them on their own lines.
 */
enum OvertimeType: string
{
    case Weekend = 'WEEKEND';
    case Holiday = 'HOLIDAY';
}
