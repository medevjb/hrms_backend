<?php

use App\Enums\PermissionName;
use App\Enums\Scope;
use App\Models\OrganizationSettings;
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

test('auth/me includes the organization timezone, even for a user with no grants (§142)', function () {
    OrganizationSettings::current()->update(['timezone' => 'Asia/Dhaka']);
    $user = User::factory()->create();
    $token = $user->createToken('phpunit')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/auth/me');

    $response->assertOk();
    $response->assertJsonPath('data.organization.timezone', 'Asia/Dhaka');
});

test('auth/me includes the resolved reporting period for every session (§85)', function () {
    OrganizationSettings::current()->update(['timezone' => 'UTC', 'reporting_month_cutoff_day' => 25]);
    $this->travelTo('2026-09-10');

    $user = User::factory()->create();
    $token = $user->createToken('phpunit')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/auth/me');

    $response->assertOk();
    $response->assertJsonPath('data.organization.reporting_month_cutoff_day', 25);
    $response->assertJsonPath('data.organization.reporting_period.key', '2026-09');
    $response->assertJsonPath('data.organization.reporting_period.start_date', '2026-08-26');
    $response->assertJsonPath('data.organization.reporting_period.end_date', '2026-09-25');
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
