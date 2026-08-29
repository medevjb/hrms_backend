<?php

namespace App\Enums;

/**
 * docs/PRD.md §24 — V1 implements WEB and MANUAL; IOT and IMPORT are named
 * in the spec as future-ready but deliberately not cased here yet (§125 —
 * don't build for a source that can't occur).
 */
enum AttendanceSource: string
{
    case Web = 'WEB';
    case Manual = 'MANUAL';
}
