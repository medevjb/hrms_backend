<?php

use App\Enums\PermissionName;
use App\Enums\Scope;
use App\Models\LeaveType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;

function userWithLeavePermission(PermissionName $permission): User
{
    $user = User::factory()->create();
    $role = Role::factory()->create();
    $perm = Permission::query()->firstOrCreate(['name' => $permission->value]);
    $role->permissions()->attach($perm);
    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id, 'scope' => Scope::AllEmployees]);

    return $user;
}

test('GET leave-types requires the leave.request or leave.policy.manage permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/leave-types')->assertStatus(403);
});

test('an employee holding leave.request can list active leave types', function () {
    LeaveType::factory()->create(['name' => 'Casual Leave']);
    $user = userWithLeavePermission(PermissionName::LeaveRequest);

    $response = $this->actingAs($user)->getJson('/api/v1/leave-types');

    $response->assertOk();
    $response->assertJsonFragment(['name' => 'Casual Leave']);
});

test('only leave.policy.manage can create a leave type', function () {
    $requester = userWithLeavePermission(PermissionName::LeaveRequest);
    $manager = userWithLeavePermission(PermissionName::LeavePolicyManage);

    $payload = [
        'name' => 'Sick Leave',
        'code' => 'SICK_LEAVE',
        'annual_allocation_days' => 10,
    ];

    $this->actingAs($requester)->postJson('/api/v1/leave-types', $payload)->assertStatus(403);

    $this->actingAs($manager)->postJson('/api/v1/leave-types', $payload)
        ->assertCreated()
        ->assertJsonPath('data.name', 'Sick Leave');
});

test('deleting a leave type deactivates it instead of removing it', function () {
    $leaveType = LeaveType::factory()->create(['is_active' => true]);
    $manager = userWithLeavePermission(PermissionName::LeavePolicyManage);

    $this->actingAs($manager)->deleteJson("/api/v1/leave-types/{$leaveType->id}")->assertNoContent();

    expect($leaveType->fresh()->is_active)->toBeFalse();
});
