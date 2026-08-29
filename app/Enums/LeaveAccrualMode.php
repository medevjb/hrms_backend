<?php

namespace App\Enums;

/** docs/PRD.md §144. */
enum LeaveAccrualMode: string
{
    case Upfront = 'UPFRONT';
    case Monthly = 'MONTHLY';
}
