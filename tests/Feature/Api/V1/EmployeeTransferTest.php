<?php

use App\Enums\PermissionName;
use App\Enums\Scope;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\UserRole;

test('transferring an employee ends the old membership and starts a new one', function () {
    $oldTeam = Team::factory()->create();
    $newTeam = Team::factory()->create();
    $employee = Employee::factory()->create();
    TeamMember::factory()->create(['team_id' => $oldTeam->id, 'employee_id' => $employee->id]);

    $user = User::factory()->create();
    $role = Role::factory()->create();
    $perm = Permission::query()->firstOrCreate(['name' => PermissionName::EmployeeUpdate->value]);
    $role->permissions()->attach($perm);
    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id, 'scope' => Scope::AllEmployees]);

    $response = $this->actingAs($user)->postJson("/api/v1/employees/{$employee->id}/transfer", [
        'team_id' => $newTeam->id,
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.team.id', $newTeam->id);
    expect($employee->teamMemberships()->count())->toBe(2);
});

test('transferring an out-of-scope employee is 404', function () {
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $outsider = Employee::factory()->create();

    $user = User::factory()->create();
    $role = Role::factory()->create();
    $perm = Permission::query()->firstOrCreate(['name' => PermissionName::EmployeeUpdate->value]);
    $role->permissions()->attach($perm);
    UserRole::factory()->create([
        'user_id' => $user->id, 'role_id' => $role->id,
        'scope' => Scope::Team, 'scope_id' => $team->id,
    ]);

    $response = $this->actingAs($user)->postJson("/api/v1/employees/{$outsider->id}/transfer", [
        'team_id' => $otherTeam->id,
    ]);

    $response->assertStatus(404);
});
