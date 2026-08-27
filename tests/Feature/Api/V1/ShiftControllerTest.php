<?php

use App\Enums\PermissionName;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Shift;
use App\Models\ShiftOverride;
use App\Models\User;
use App\Models\UserRole;

function userWithShiftPermission(PermissionName $permission): User
{
    $user = User::factory()->create();
    $role = Role::factory()->create();
    $perm = Permission::query()->firstOrCreate(['name' => $permission->value]);
    $role->permissions()->attach($perm);
    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id]);

    return $user;
}

test('listing shifts requires shift.view', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/shifts')->assertStatus(403);
});

test('creating a shift requires shift.manage', function () {
    $user = userWithShiftPermission(PermissionName::ShiftView);

    $this->actingAs($user)->postJson('/api/v1/shifts', [
        'name' => 'Standard', 'start_time' => '09:00', 'end_time' => '18:00', 'expected_work_minutes' => 480,
    ])->assertStatus(403);
});

test('a user with shift.manage can create a shift', function () {
    $user = userWithShiftPermission(PermissionName::ShiftManage);

    $response = $this->actingAs($user)->postJson('/api/v1/shifts', [
        'name' => 'Night Shift',
        'start_time' => '20:00',
        'end_time' => '05:00',
        'expected_work_minutes' => 480,
        'late_grace_minutes' => 20,
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('data.name', 'Night Shift');
    $response->assertJsonPath('data.start_time', '20:00');
    $response->assertJsonPath('data.end_time', '05:00');
    $response->assertJsonPath('data.is_overnight', true);
    $response->assertJsonPath('data.late_grace_minutes', 20);
});

test('updating a shift', function () {
    $shift = Shift::factory()->create(['name' => 'Standard']);
    $user = userWithShiftPermission(PermissionName::ShiftManage);

    $response = $this->actingAs($user)->putJson("/api/v1/shifts/{$shift->id}", ['name' => 'Renamed']);

    $response->assertOk();
    $response->assertJsonPath('data.name', 'Renamed');
});

test('shift start_time and end_time must be H:i formatted', function () {
    $user = userWithShiftPermission(PermissionName::ShiftManage);

    $response = $this->actingAs($user)->postJson('/api/v1/shifts', [
        'name' => 'Bad', 'start_time' => 'nine am', 'end_time' => '18:00', 'expected_work_minutes' => 480,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['start_time']);
});

test('creating a shift override requires shift.override, not just shift.manage', function () {
    $shift = Shift::factory()->create();
    $employee = Employee::factory()->create();
    $user = userWithShiftPermission(PermissionName::ShiftManage);

    $this->actingAs($user)->postJson('/api/v1/shift-overrides', [
        'employee_id' => $employee->id,
        'shift_id' => $shift->id,
        'work_date' => '2026-08-20',
        'reason' => 'Client meeting',
    ])->assertStatus(403);
});

test('a user with shift.override can set a one-day shift change and it records who changed it', function () {
    $shift = Shift::factory()->create();
    $employee = Employee::factory()->create();
    $user = userWithShiftPermission(PermissionName::ShiftOverride);

    $response = $this->actingAs($user)->postJson('/api/v1/shift-overrides', [
        'employee_id' => $employee->id,
        'shift_id' => $shift->id,
        'work_date' => '2026-08-20',
        'reason' => 'Client meeting',
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('data.reason', 'Client meeting');
    $response->assertJsonPath('data.changed_by', $user->id);
    $response->assertJsonPath('data.shift.id', $shift->id);
});

test('only one shift override may exist per employee per work date', function () {
    $employee = Employee::factory()->create();
    ShiftOverride::factory()->create(['employee_id' => $employee->id, 'work_date' => '2026-08-20']);
    $shift = Shift::factory()->create();
    $user = userWithShiftPermission(PermissionName::ShiftOverride);

    $response = $this->actingAs($user)->postJson('/api/v1/shift-overrides', [
        'employee_id' => $employee->id,
        'shift_id' => $shift->id,
        'work_date' => '2026-08-20',
        'reason' => 'Second attempt',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['work_date']);
});
