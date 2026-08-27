<?php

namespace App\Enums;

/** docs/PRD.md §13 — the complete V1 employee status set. */
enum EmployeeStatus: string
{
    case Invited = 'INVITED';
    case Active = 'ACTIVE';
    case Probation = 'PROBATION';
    case NoticePeriod = 'NOTICE_PERIOD';
    case Suspended = 'SUSPENDED';
    case Resigned = 'RESIGNED';
    case Terminated = 'TERMINATED';
    case Archived = 'ARCHIVED';
}
