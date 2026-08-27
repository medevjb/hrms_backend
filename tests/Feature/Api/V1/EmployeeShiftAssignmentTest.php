<?php

use App\Enums\PermissionName;
use App\Enums\Scope;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Shift;
use App\Models\Team;
use App\Models\User;
use App\Models\UserRole;

test('assigning a shift ends the old assignment and starts a new one', function () {
    $oldShift = Shift::factory()->create();
    $newShift = Shift::factory()->create();
    $employee = Employee::factory()->create();
    EmployeeShift::factory()->create(['employee_id' => $employee->id, 'shift_id' => $oldShift->id]);

    $user = User::factory()->create();
    $role = Role::factory()->create();
    $perm = Permission::query()->firstOrCreate(['name' => PermissionName::EmployeeUpdate->value]);
    $role->permissions()->attach($perm);
    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id, 'scope' => Scope::AllEmployees]);

    $response = $this->actingAs($user)->postJson("/api/v1/employees/{$employee->id}/assign-shift", [
        'shift_id' => $newShift->id,
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.current_shift.id', $newShift->id);
    expect($employee->shiftAssignments()->count())->toBe(2);
    expect($employee->currentShiftAssignment->shift_id)->toBe($newShift->id);
});

test('assigning a shift to an out-of-scope employee is 404', function () {
    $team = Team::factory()->create();
    $outsider = Employee::factory()->create();
    $shift = Shift::factory()->create();

    $user = User::factory()->create();
    $role = Role::factory()->create();
    $perm = Permission::query()->firstOrCreate(['name' => PermissionName::EmployeeUpdate->value]);
    $role->permissions()->attach($perm);
    UserRole::factory()->create([
        'user_id' => $user->id, 'role_id' => $role->id,
        'scope' => Scope::Team, 'scope_id' => $team->id,
    ]);

    $response = $this->actingAs($user)->postJson("/api/v1/employees/{$outsider->id}/assign-shift", [
        'shift_id' => $shift->id,
    ]);

    $response->assertStatus(404);
});
