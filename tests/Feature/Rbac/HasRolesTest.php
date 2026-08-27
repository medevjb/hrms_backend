<?php

use App\Enums\PermissionName;
use App\Enums\Scope;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;

test('a user with a role assignment has that permission', function () {
    $user = User::factory()->create();
    $role = Role::factory()->create();
    $permission = Permission::factory()->create(['name' => PermissionName::AttendanceView->value]);
    $role->permissions()->attach($permission);

    UserRole::factory()->create([
        'user_id' => $user->id,
        'role_id' => $role->id,
        'scope' => Scope::Team,
    ]);

    expect($user->hasPermission(PermissionName::AttendanceView))->toBeTrue();
    expect($user->hasPermission(PermissionName::PayrollFinalize))->toBeFalse();
});

test('a user without any role assignment has no permissions', function () {
    $user = User::factory()->create();

    expect($user->hasPermission(PermissionName::AttendanceView))->toBeFalse();
    expect($user->hasRole('Admin'))->toBeFalse();
});

test('hasRole checks the assigned role name', function () {
    $user = User::factory()->create();
    $role = Role::factory()->create(['name' => 'Team Leader']);

    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id]);

    expect($user->hasRole('Team Leader'))->toBeTrue();
    expect($user->hasRole('Admin'))->toBeFalse();
});

test('scopesFor returns every grant carrying the permission, with its scope', function () {
    $user = User::factory()->create();

    $teamRole = Role::factory()->create(['name' => 'Team Leader']);
    $hrRole = Role::factory()->create(['name' => 'HR']);
    $permission = Permission::factory()->create(['name' => PermissionName::LeaveApprove->value]);
    $teamRole->permissions()->attach($permission);
    $hrRole->permissions()->attach($permission);

    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $teamRole->id, 'scope' => Scope::Team, 'scope_id' => 7]);
    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $hrRole->id, 'scope' => Scope::HrScope]);

    $grants = $user->scopesFor(PermissionName::LeaveApprove);

    expect($grants)->toHaveCount(2);
    expect($grants->pluck('scope')->map(fn (Scope $s) => $s->value)->all())
        ->toEqualCanonicalizing(['TEAM', 'HR_SCOPE']);
});

test('a user can hold the same role at two different scopes', function () {
    $user = User::factory()->create();
    $role = Role::factory()->create(['name' => 'Team Leader']);

    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id, 'scope' => Scope::Team, 'scope_id' => 1]);
    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id, 'scope' => Scope::Team, 'scope_id' => 2]);

    expect($user->roleAssignments()->count())->toBe(2);
    expect($user->hasRole('Team Leader'))->toBeTrue();
});
