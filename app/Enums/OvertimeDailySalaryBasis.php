<?php

namespace App\Enums;

/** docs/PRD.md §143 */
enum OvertimeDailySalaryBasis: string
{
    case Basic = 'BASIC';
    case Gross = 'GROSS';
}
