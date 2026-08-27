<?php

namespace App\Services;

use App\Enums\PermissionName;
use App\Enums\Scope;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Collection;

/**
 * Turns "this user holds this permission" into "for which employees". This
 * is the extension point flagged as missing when Phase 1 shipped — Team,
 * Department, and Employee now exist, so grants can finally be resolved
 * into a real employee-ID set (docs/PRD.md §10).
 */
class ScopeResolver
{
    /**
     * @return list<int>|null null means unrestricted (every employee);
     *                        otherwise the exact set of employee ids the
     *                        user may see through this permission. An empty
     *                        list means the user holds the permission
     *                        nowhere and should see nothing.
     */
    public function employeeIdsFor(User $user, PermissionName|string $permission): ?array
    {
        $grants = $user->scopesFor($permission);

        if ($grants->isEmpty()) {
            return [];
        }

        // HR_SCOPE is unrestricted for V1 — see Scope::needsScopeId().
        $unrestrictedScopes = [Scope::AllEmployees, Scope::HrScope, Scope::System];

        if ($grants->contains(fn (UserRole $grant) => in_array($grant->scope, $unrestrictedScopes, true))) {
            return null;
        }

        return array_values($grants
            ->flatMap(fn (UserRole $grant) => $this->resolveGrant($user, $grant))
            ->filter()
            ->unique()
            ->all());
    }

    /**
     * @return Collection<int, int>
     */
    private function resolveGrant(User $user, UserRole $grant): Collection
    {
        return match ($grant->scope) {
            Scope::Self => collect([$user->employee?->id]),
            Scope::Team => $this->teamEmployeeIds($grant->scope_id),
            // Operation resolves identically to Department for V1: an
            // Operation Manager's "operation" is the department they're
            // assigned to (departments.operation_manager_id) — see §14.
            Scope::Department, Scope::Operation => $this->departmentEmployeeIds($grant->scope_id),
            Scope::AllEmployees, Scope::HrScope, Scope::System => collect(),
        };
    }

    /**
     * @return Collection<int, int>
     */
    private function teamEmployeeIds(?int $teamId): Collection
    {
        if ($teamId === null) {
            return collect();
        }

        return TeamMember::query()
            ->where('team_id', $teamId)
            ->whereNull('ended_at')
            ->pluck('employee_id');
    }

    /**
     * @return Collection<int, int>
     */
    private function departmentEmployeeIds(?int $departmentId): Collection
    {
        if ($departmentId === null) {
            return collect();
        }

        $teamIds = Team::query()->where('department_id', $departmentId)->pluck('id');

        return TeamMember::query()
            ->whereIn('team_id', $teamIds)
            ->whereNull('ended_at')
            ->pluck('employee_id');
    }
}
