<?php

use App\Enums\PermissionName;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;

test('Gate::before resolves a permission string ability from the users grants', function () {
    $user = User::factory()->create();
    $role = Role::factory()->create();
    $permission = Permission::factory()->create(['name' => PermissionName::AttendanceView->value]);
    $role->permissions()->attach($permission);
    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id]);

    expect($user->can(PermissionName::AttendanceView->value))->toBeTrue();
    expect($user->can(PermissionName::PayrollFinalize->value))->toBeFalse();
});

test('an unrelated ability string is not swallowed by the permission gate', function () {
    $user = User::factory()->create();

    // "some-random-ability" isn't a PermissionName case and has no Gate::define
    // or Policy — Laravel denies it, the same as before this provider existed.
    expect($user->can('some-random-ability'))->toBeFalse();
});
