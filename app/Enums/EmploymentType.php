<?php

namespace App\Enums;

/**
 * docs/PRD.md §12 lists "employment type" as an employee field without
 * enumerating values — this is the V1 set; add cases here as the business
 * needs them, never as a bare string on the model.
 */
enum EmploymentType: string
{
    case FullTime = 'FULL_TIME';
    case PartTime = 'PART_TIME';
    case Contract = 'CONTRACT';
    case Intern = 'INTERN';
}
