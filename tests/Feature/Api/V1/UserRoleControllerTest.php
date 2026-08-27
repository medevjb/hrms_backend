<?php

use App\Enums\PermissionName;
use App\Enums\Scope;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;

function actingAdmin(): User
{
    $admin = User::factory()->create();
    $role = Role::factory()->create();
    $permission = Permission::factory()->create(['name' => PermissionName::SettingsManage->value]);
    $role->permissions()->attach($permission);
    UserRole::factory()->create(['user_id' => $admin->id, 'role_id' => $role->id, 'scope' => Scope::System]);

    return $admin;
}

test('a user without settings.manage cannot assign roles', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create();
    $role = Role::factory()->create();

    $response = $this->actingAs($actor)->postJson("/api/v1/users/{$target->id}/roles", [
        'role_id' => $role->id,
        'scope' => Scope::Team->value,
        'scope_id' => 1,
    ]);

    $response->assertStatus(403);
    $response->assertJson(['code' => 'UNAUTHORIZED']);
});

test('a user with settings.manage can assign a role at a scope', function () {
    $admin = actingAdmin();
    $target = User::factory()->create();
    $role = Role::factory()->create(['name' => 'Team Leader']);

    $response = $this->actingAs($admin)->postJson("/api/v1/users/{$target->id}/roles", [
        'role_id' => $role->id,
        'scope' => Scope::Team->value,
        'scope_id' => 5,
    ]);

    $response->assertStatus(201);
    $response->assertJson([
        'data' => [
            'user_id' => $target->id,
            'role' => ['name' => 'Team Leader'],
            'scope' => 'TEAM',
            'scope_id' => 5,
        ],
    ]);

    expect($target->fresh()->roleAssignments)->toHaveCount(1);
});

test('a scope that needs a scope_id is rejected without one', function () {
    $admin = actingAdmin();
    $target = User::factory()->create();
    $role = Role::factory()->create();

    $response = $this->actingAs($admin)->postJson("/api/v1/users/{$target->id}/roles", [
        'role_id' => $role->id,
        'scope' => Scope::Team->value,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['scope_id']);
});

test('a scope that never needs a scope_id is rejected if one is given', function () {
    $admin = actingAdmin();
    $target = User::factory()->create();
    $role = Role::factory()->create();

    $response = $this->actingAs($admin)->postJson("/api/v1/users/{$target->id}/roles", [
        'role_id' => $role->id,
        'scope' => Scope::AllEmployees->value,
        'scope_id' => 1,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['scope_id']);
});

test('an admin can list a users role assignments', function () {
    $admin = actingAdmin();
    $target = User::factory()->create();
    $role = Role::factory()->create();
    UserRole::factory()->create(['user_id' => $target->id, 'role_id' => $role->id]);

    $response = $this->actingAs($admin)->getJson("/api/v1/users/{$target->id}/roles");

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
});

test('an admin can revoke a role assignment', function () {
    $admin = actingAdmin();
    $target = User::factory()->create();
    $role = Role::factory()->create();
    $grant = UserRole::factory()->create(['user_id' => $target->id, 'role_id' => $role->id]);

    $response = $this->actingAs($admin)->deleteJson("/api/v1/users/{$target->id}/roles/{$grant->id}");

    $response->assertNoContent();
    expect(UserRole::find($grant->id))->toBeNull();
});

test('revoking a grant that does not belong to the given user is not found', function () {
    $admin = actingAdmin();
    $target = User::factory()->create();
    $otherUser = User::factory()->create();
    $role = Role::factory()->create();
    $grant = UserRole::factory()->create(['user_id' => $otherUser->id, 'role_id' => $role->id]);

    $response = $this->actingAs($admin)->deleteJson("/api/v1/users/{$target->id}/roles/{$grant->id}");

    $response->assertStatus(404);
});
