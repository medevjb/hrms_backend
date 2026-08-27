<?php

namespace App\Enums;

/** docs/PRD.md §65 */
enum SalaryDayCalculationMethod: string
{
    case Fixed30Days = 'FIXED_30_DAYS';
    case CalendarDays = 'CALENDAR_DAYS';
    case WorkingDays = 'WORKING_DAYS';
}
