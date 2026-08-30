<?php

namespace App\Enums;

/**
 * docs/PRD.md §147 — HR either upholds the dispute (an adjustment follows,
 * the entry recalculates) or rejects it (an explanation is recorded).
 */
enum PayrollDisputeResolution: string
{
    case Upheld = 'UPHELD';
    case Rejected = 'REJECTED';
}
