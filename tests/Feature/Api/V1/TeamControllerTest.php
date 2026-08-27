<?php

use App\Enums\PermissionName;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\UserRole;

function userWithTeamPermission(PermissionName $permission): User
{
    $user = User::factory()->create();
    $role = Role::factory()->create();
    $perm = Permission::query()->firstOrCreate(['name' => $permission->value]);
    $role->permissions()->attach($perm);
    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id]);

    return $user;
}

test('creating a team requires team.manage', function () {
    $department = Department::factory()->create();
    $user = userWithTeamPermission(PermissionName::TeamView);

    $this->actingAs($user)->postJson('/api/v1/teams', [
        'department_id' => $department->id,
        'name' => 'Backend',
    ])->assertStatus(403);
});

test('a user with team.manage can create a team and assign a team leader', function () {
    $department = Department::factory()->create();
    $leader = Employee::factory()->create();
    $user = userWithTeamPermission(PermissionName::TeamManage);

    $response = $this->actingAs($user)->postJson('/api/v1/teams', [
        'department_id' => $department->id,
        'name' => 'Backend',
        'team_leader_id' => $leader->id,
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('data.name', 'Backend');
    $response->assertJsonPath('data.team_leader.id', $leader->id);
});

test('a newly created team is active by default in the response, not just the database', function () {
    $department = Department::factory()->create();
    $user = userWithTeamPermission(PermissionName::TeamManage);

    $response = $this->actingAs($user)->postJson('/api/v1/teams', [
        'department_id' => $department->id,
        'name' => 'Sales',
    ]);

    $response->assertJsonPath('data.active', true);
});

test('team names are unique within a department but not across departments', function () {
    $departmentA = Department::factory()->create();
    $departmentB = Department::factory()->create();
    Team::factory()->create(['department_id' => $departmentA->id, 'name' => 'Backend']);
    $user = userWithTeamPermission(PermissionName::TeamManage);

    $duplicate = $this->actingAs($user)->postJson('/api/v1/teams', [
        'department_id' => $departmentA->id, 'name' => 'Backend',
    ]);
    $duplicate->assertStatus(422);

    $allowed = $this->actingAs($user)->postJson('/api/v1/teams', [
        'department_id' => $departmentB->id, 'name' => 'Backend',
    ]);
    $allowed->assertStatus(201);
});

test('adding a member to a team via the team endpoint transfers them', function () {
    $team = Team::factory()->create();
    $employee = Employee::factory()->create();
    $user = userWithTeamPermission(PermissionName::TeamManage);

    $response = $this->actingAs($user)->postJson("/api/v1/teams/{$team->id}/members", [
        'employee_id' => $employee->id,
    ]);

    $response->assertStatus(201);
    expect($employee->fresh()->currentTeam()->id)->toBe($team->id);
});

test('listing team members only shows current members', function () {
    $team = Team::factory()->create();
    $current = Employee::factory()->create();
    $former = Employee::factory()->create();
    TeamMember::factory()->create(['team_id' => $team->id, 'employee_id' => $current->id]);
    TeamMember::factory()->ended()->create(['team_id' => $team->id, 'employee_id' => $former->id]);
    $user = userWithTeamPermission(PermissionName::TeamView);

    $response = $this->actingAs($user)->getJson("/api/v1/teams/{$team->id}/members");

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.employee.id', $current->id);
});

test('removing a member ends their membership without starting a new one', function () {
    $team = Team::factory()->create();
    $employee = Employee::factory()->create();
    TeamMember::factory()->create(['team_id' => $team->id, 'employee_id' => $employee->id]);
    $user = userWithTeamPermission(PermissionName::TeamManage);

    $response = $this->actingAs($user)->deleteJson("/api/v1/teams/{$team->id}/members/{$employee->id}");

    $response->assertNoContent();
    expect($employee->fresh()->currentTeam())->toBeNull();
});

test('removing a member who does not belong to this team is 404', function () {
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $employee = Employee::factory()->create();
    TeamMember::factory()->create(['team_id' => $otherTeam->id, 'employee_id' => $employee->id]);
    $user = userWithTeamPermission(PermissionName::TeamManage);

    $response = $this->actingAs($user)->deleteJson("/api/v1/teams/{$team->id}/members/{$employee->id}");

    $response->assertStatus(404);
});
