<?php

namespace App\Concerns;

use App\Enums\PermissionName;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UserRole;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection as BaseCollection;

/**
 * docs/PRD.md §10 — "Role + Permission + Scope = Access". A user can hold the
 * same role at different scopes (Team Leader of Team A, not Team B), so every
 * check here goes through the user_roles grants, never a role name alone.
 */
trait HasRoles
{
    /**
     * @return HasMany<UserRole, $this>
     */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function hasRole(string $roleName): bool
    {
        return $this->roleAssignments()
            ->whereHas('role', fn ($query) => $query->where('name', $roleName))
            ->exists();
    }

    /**
     * Does this user hold the permission through ANY role assignment,
     * regardless of scope? This is what Gate::before checks (§18) — it
     * answers "can they do this at all", not "for whom". Once Phase 2
     * exists, endpoints that return employee-bound data additionally
     * narrow by scopesFor() through ScopeResolver.
     */
    public function hasPermission(PermissionName|string $permission): bool
    {
        $permissionName = $permission instanceof PermissionName ? $permission->value : $permission;

        return $this->roleAssignments()
            ->whereHas(
                'role.permissions',
                fn ($query) => $query->where('name', $permissionName),
            )
            ->exists();
    }

    /**
     * Every grant giving this user the permission, so a caller can inspect
     * the scope/scope_id pairs — the extension point ScopeResolver (Phase 2)
     * builds on to turn a permission into an employee-ID set.
     *
     * @return Collection<int, UserRole>
     */
    public function scopesFor(PermissionName|string $permission): Collection
    {
        $permissionName = $permission instanceof PermissionName ? $permission->value : $permission;

        return $this->roleAssignments()
            ->with('role')
            ->whereHas(
                'role.permissions',
                fn ($query) => $query->where('name', $permissionName),
            )
            ->get();
    }

    /**
     * @return Collection<int, Role>
     */
    public function roles(): Collection
    {
        return Role::query()
            ->whereHas('userRoles', fn ($query) => $query->where('user_id', $this->getKey()))
            ->get();
    }

    /**
     * Every distinct permission name this user holds through any role
     * assignment, regardless of scope. Feeds /auth/me (§92.2) so the
     * frontend can hide/show controls — display-only, never a security
     * boundary; the real check is hasPermission() on the backend.
     *
     * @return BaseCollection<int, string>
     */
    public function permissionNames(): BaseCollection
    {
        return Permission::query()
            ->whereHas(
                'roles.userRoles',
                fn ($query) => $query->where('user_id', $this->getKey()),
            )
            ->pluck('name')
            ->unique()
            ->values();
    }
}
