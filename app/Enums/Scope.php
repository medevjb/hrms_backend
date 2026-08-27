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
     * Scopes above that are grants over other employees, not just the
     * grantee's own record. Team/Department/Operation/HrScope need a
     * scope_id (a team, department, or similar) once Phase 2 exists;
     * Self/AllEmployees/System never do.
     */
    public function needsScopeId(): bool
    {
        return match ($this) {
            self::Team, self::Department, self::Operation, self::HrScope => true,
            self::Self, self::AllEmployees, self::System => false,
        };
    }
}
