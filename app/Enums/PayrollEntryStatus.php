<?php

namespace App\Enums;

/**
 * docs/PRD.md §69 — one employee's line in a payroll period. Phase 8 stops
 * at CALCULATED (draft net computed); PREPARED / RELEASED / acknowledgement
 * and the §147 dispute states arrive in Phase 9.
 */
enum PayrollEntryStatus: string
{
    case Draft = 'DRAFT';
    case Calculated = 'CALCULATED';
    case Prepared = 'PREPARED';
    case Released = 'RELEASED';
    case Finalized = 'FINALIZED';
}
