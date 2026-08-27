<?php

namespace App\Enums;

/**
 * docs/PRD.md §54 lists a "type" field without enumerating values —
 * this is the V1 set.
 */
enum HolidayType: string
{
    case National = 'NATIONAL';
    case Religious = 'RELIGIOUS';
    case Company = 'COMPANY';
    case Other = 'OTHER';
}
