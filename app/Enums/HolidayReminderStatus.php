<?php

namespace App\Enums;

/**
 * docs/PRD.md §55 + §84 mismatch table — one row per holiday records that
 * the five-day scan has already fired, so a daily cron never re-creates
 * the reminder or re-pings Head HR. PENDING while the notice waits on
 * Head HR, ACTIONED once the notice is published, DISMISSED if waved off.
 */
enum HolidayReminderStatus: string
{
    case Pending = 'PENDING';
    case Actioned = 'ACTIONED';
    case Dismissed = 'DISMISSED';
}
