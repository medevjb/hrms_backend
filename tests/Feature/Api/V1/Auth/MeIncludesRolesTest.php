<?php

use App\Enums\PermissionName;
use App\Enums\Scope;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;

test('auth/me includes the users roles and resolved permissions', function () {
    $user = User::factory()->create();
    $role = Role::factory()->create(['name' => 'Team Leader']);
    $permission = Permission::factory()->create(['name' => PermissionName::AttendanceView->value]);
    $role->permissions()->attach($permission);
    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id, 'scope' => Scope::Team]);

    $token = $user->createToken('phpunit')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/auth/me');

    $response->assertOk();
    $response->assertJson([
        'data' => [
            'roles' => ['Team Leader'],
            'permissions' => [PermissionName::AttendanceView->value],
        ],
    ]);
});

test('auth/me returns empty roles and permissions for a user with no grants', function () {
    $user = User::factory()->create();
    $token = $user->createToken('phpunit')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/auth/me');

    $response->assertOk();
    $response->assertJson(['data' => ['roles' => [], 'permissions' => []]]);
});

test('login response also includes roles and permissions', function () {
    $user = User::factory()->create();
    $role = Role::factory()->create(['name' => 'Admin']);
    $permission = Permission::factory()->create(['name' => PermissionName::SettingsManage->value]);
    $role->permissions()->attach($permission);
    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id, 'scope' => Scope::AllEmployees]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'phpunit',
    ]);

    $response->assertOk();
    $response->assertJson([
        'data' => [
            'user' => [
                'roles' => ['Admin'],
                'permissions' => [PermissionName::SettingsManage->value],
            ],
        ],
    ]);
});
