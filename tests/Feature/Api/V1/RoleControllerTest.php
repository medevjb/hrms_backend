<?php

use App\Enums\PermissionName;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\RolePermissionSeeder;

function userWithRolePermission(PermissionName $permission): User
{
    $user = User::factory()->create();
    $role = Role::factory()->create();
    $perm = Permission::query()->firstOrCreate(['name' => $permission->value]);
    $role->permissions()->attach($perm);
    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id]);

    return $user;
}

test('listing roles requires settings.manage', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/roles')->assertStatus(403);
});

test('a user with settings.manage sees the full seeded catalogue with permissions', function () {
    $this->seed(RolePermissionSeeder::class);
    $user = userWithRolePermission(PermissionName::SettingsManage);

    $response = $this->actingAs($user)->getJson('/api/v1/roles');

    $response->assertOk();
    expect($response->json('data.*.name'))->toContain(
        'Admin', 'Head of HR', 'HR', 'Operation Manager', 'Team Leader', 'Team Member', 'System Admin / DevOps',
    );
    // id order mirrors the seed order — Admin first.
    $response->assertJsonPath('data.0.name', 'Admin');
    $response->assertJsonPath('data.0.permission_count', count(PermissionName::cases()));
    expect($response->json('data.0.permissions'))->toContain(PermissionName::PayrollFinalize->value);
    expect($response->json('data.0.description'))->not->toBeNull();
});

test('a single role reports how many users hold it', function () {
    $this->seed(RolePermissionSeeder::class);
    $admin = Role::where('name', 'Admin')->firstOrFail();
    UserRole::factory()->count(2)->create(['role_id' => $admin->id]);

    $user = userWithRolePermission(PermissionName::SettingsManage);

    $response = $this->actingAs($user)->getJson("/api/v1/roles/{$admin->id}");

    $response->assertOk();
    $response->assertJsonPath('data.name', 'Admin');
    $response->assertJsonPath('data.assigned_user_count', 2);
});

test('an unknown role id is a 404', function () {
    $user = userWithRolePermission(PermissionName::SettingsManage);

    $this->actingAs($user)->getJson('/api/v1/roles/999')->assertStatus(404);
});
