<?php

use App\Enums\PermissionName;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;

function userWithDeptPermission(PermissionName $permission): User
{
    $user = User::factory()->create();
    $role = Role::factory()->create();
    $perm = Permission::query()->firstOrCreate(['name' => $permission->value]);
    $role->permissions()->attach($perm);
    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id]);

    return $user;
}

test('listing departments requires department.view', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/departments')->assertStatus(403);
});

test('creating a department requires department.manage', function () {
    $user = userWithDeptPermission(PermissionName::DepartmentView);

    $this->actingAs($user)->postJson('/api/v1/departments', ['name' => 'Engineering'])
        ->assertStatus(403);
});

test('a user with department.manage can create and assign an operation manager', function () {
    $manager = Employee::factory()->create();
    $user = userWithDeptPermission(PermissionName::DepartmentManage);

    $response = $this->actingAs($user)->postJson('/api/v1/departments', [
        'name' => 'Engineering',
        'operation_manager_id' => $manager->id,
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('data.name', 'Engineering');
    $response->assertJsonPath('data.operation_manager.id', $manager->id);
});

test('a newly created department is active by default in the response, not just the database', function () {
    $user = userWithDeptPermission(PermissionName::DepartmentManage);

    $response = $this->actingAs($user)->postJson('/api/v1/departments', ['name' => 'Sales']);

    $response->assertJsonPath('data.active', true);
});

test('department names must be unique', function () {
    Department::factory()->create(['name' => 'Engineering']);
    $user = userWithDeptPermission(PermissionName::DepartmentManage);

    $response = $this->actingAs($user)->postJson('/api/v1/departments', ['name' => 'Engineering']);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name']);
});

test('updating a department reassigns its operation manager', function () {
    $department = Department::factory()->create();
    $newManager = Employee::factory()->create();
    $user = userWithDeptPermission(PermissionName::DepartmentManage);

    $response = $this->actingAs($user)->putJson("/api/v1/departments/{$department->id}", [
        'operation_manager_id' => $newManager->id,
    ]);

    $response->assertOk();
    expect($department->fresh()->operation_manager_id)->toBe($newManager->id);
});
