<?php

namespace App\Enums;

/**
 * docs/PRD.md §10 — paired with a permission at grant time: "attendance.view @ TEAM"
 * means something, "attendance.view" alone does not.
 */
enum Scope: string
{
    case Self = 'SELF';
    case Team = 'TEAM';
    case Department = 'DEPARTMENT';
    case Operation = 'OPERATION';
    case HrScope = 'HR_SCOPE';
    case AllEmployees = 'ALL_EMPLOYEES';
    case System = 'SYSTEM';

    /**
     * Team/Department/Operation point at a specific team/department id.
     * HrScope needs none: V1 has no HR-territory table to subdivide the
     * workforce by, so ScopeResolver treats it as unrestricted, same as
     * AllEmployees — see app/Services/ScopeResolver.php. Self/AllEmployees/
     * System never need one either.
     */
    public function needsScopeId(): bool
    {
        return match ($this) {
            self::Team, self::Department, self::Operation => true,
            self::Self, self::HrScope, self::AllEmployees, self::System => false,
        };
    }
}
