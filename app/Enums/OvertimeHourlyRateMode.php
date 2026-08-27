<?php

namespace App\Enums;

/** docs/PRD.md §48 */
enum OvertimeHourlyRateMode: string
{
    case Fixed = 'FIXED';
    case SalaryDerived = 'SALARY_DERIVED';
}
