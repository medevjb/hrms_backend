<?php

namespace App\Enums;

/**
 * docs/PRD.md §29 — the complete V1 attendance status set. PRESENT/LATE are
 * produced at check-in; every other status is produced by the nightly close
 * job (§137). MANUALLY_ADJUSTED is deliberately not a case here — §29 is
 * explicit that it's a marker (AttendanceRecord::is_manual_adjustment), not
 * a classification, so a corrected day keeps its real status and still
 * counts correctly in reports and payroll.
 */
enum AttendanceStatus: string
{
    case Present = 'PRESENT';
    case Late = 'LATE';
    case Absent = 'ABSENT';
    case OnLeave = 'ON_LEAVE';
    case Holiday = 'HOLIDAY';
    case Weekend = 'WEEKEND';
    case HalfDay = 'HALF_DAY';
    case MissingCheckout = 'MISSING_CHECKOUT';
}
