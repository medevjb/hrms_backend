<?php

namespace App\Enums;

/**
 * docs/PRD.md §55/§56 — a holiday notice is drafted by the daily scan
 * (ScanHolidayNoticesCommand) in PENDING_APPROVAL, and only ever leaves
 * that state when Head HR signs it (PUBLISHED) or waves it off (DISMISSED).
 * "No notice is automatically published without Head HR approval" (§55).
 */
enum HolidayNoticeStatus: string
{
    case PendingApproval = 'PENDING_APPROVAL';
    case Published = 'PUBLISHED';
    case Dismissed = 'DISMISSED';
}
