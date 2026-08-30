<?php

namespace App\Enums;

/**
 * docs/PRD.md §70/§147 — where an employee's payroll entry sits in the
 * confirmation lifecycle once it has been released to them. PENDING until
 * they act (or the §147 dispute window auto-acknowledges it); DISPUTED
 * blocks that one entry's finalisation until RESOLVED.
 */
enum PayrollAcknowledgementStatus: string
{
    case Pending = 'PENDING';
    case Acknowledged = 'ACKNOWLEDGED';
    case Disputed = 'DISPUTED';
    case Resolved = 'RESOLVED';
    case AutoAcknowledged = 'AUTO_ACKNOWLEDGED';

    public function blocksFinalization(): bool
    {
        return $this === self::Disputed;
    }
}
