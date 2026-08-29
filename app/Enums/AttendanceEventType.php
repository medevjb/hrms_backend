<?php

namespace App\Enums;

/** docs/PRD.md §24 — raw attendance actions. */
enum AttendanceEventType: string
{
    case CheckIn = 'CHECK_IN';
    case CheckOut = 'CHECK_OUT';
}
