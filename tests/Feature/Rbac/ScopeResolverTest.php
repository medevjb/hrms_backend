<?php

use App\Enums\PermissionName;
use App\Enums\Scope;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\UserRole;
use App\Services\ScopeResolver;

function grantPermission(User $user, PermissionName $permission, Scope $scope, ?int $scopeId = null): void
{
    $role = Role::factory()->create();
    $perm = Permission::query()->firstOrCreate(['name' => $permission->value]);
    $role->permissions()->attach($perm);
    UserRole::factory()->create([
        'user_id' => $user->id,
        'role_id' => $role->id,
        'scope' => $scope,
        'scope_id' => $scopeId,
    ]);
}

test('a user with no grant for the permission sees nobody', function () {
    $user = User::factory()->create();

    $ids = app(ScopeResolver::class)->employeeIdsFor($user, PermissionName::AttendanceView);

    expect($ids)->toBe([]);
});

test('ALL_EMPLOYEES resolves to unrestricted (null)', function () {
    $user = User::factory()->create();
    grantPermission($user, PermissionName::AttendanceView, Scope::AllEmployees);

    $ids = app(ScopeResolver::class)->employeeIdsFor($user, PermissionName::AttendanceView);

    expect($ids)->toBeNull();
});

test('HR_SCOPE resolves to unrestricted (null) for V1', function () {
    $user = User::factory()->create();
    grantPermission($user, PermissionName::AttendanceView, Scope::HrScope);

    $ids = app(ScopeResolver::class)->employeeIdsFor($user, PermissionName::AttendanceView);

    expect($ids)->toBeNull();
});

test('SELF resolves to only the grantees own employee id', function () {
    $user = User::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    grantPermission($user, PermissionName::AttendanceView, Scope::Self);

    $ids = app(ScopeResolver::class)->employeeIdsFor($user, PermissionName::AttendanceView);

    expect($ids)->toBe([$employee->id]);
});

test('SELF with no linked employee resolves to nobody', function () {
    $user = User::factory()->create();
    grantPermission($user, PermissionName::AttendanceView, Scope::Self);

    $ids = app(ScopeResolver::class)->employeeIdsFor($user, PermissionName::AttendanceView);

    expect($ids)->toBe([]);
});

test('TEAM resolves to current members of that team only', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $memberA = Employee::factory()->create();
    $memberB = Employee::factory()->create();
    $outsider = Employee::factory()->create();
    $former = Employee::factory()->create();

    TeamMember::factory()->create(['team_id' => $team->id, 'employee_id' => $memberA->id]);
    TeamMember::factory()->create(['team_id' => $team->id, 'employee_id' => $memberB->id]);
    TeamMember::factory()->create(['team_id' => $otherTeam->id, 'employee_id' => $outsider->id]);
    TeamMember::factory()->ended()->create(['team_id' => $team->id, 'employee_id' => $former->id]);

    grantPermission($user, PermissionName::AttendanceView, Scope::Team, $team->id);

    $ids = app(ScopeResolver::class)->employeeIdsFor($user, PermissionName::AttendanceView);

    expect($ids)->toEqualCanonicalizing([$memberA->id, $memberB->id]);
});

test('DEPARTMENT resolves to every current member of every team in that department', function () {
    $user = User::factory()->create();
    $department = Department::factory()->create();
    $otherDepartment = Department::factory()->create();
    $teamOne = Team::factory()->create(['department_id' => $department->id]);
    $teamTwo = Team::factory()->create(['department_id' => $department->id]);
    $teamElsewhere = Team::factory()->create(['department_id' => $otherDepartment->id]);

    $memberOne = Employee::factory()->create();
    $memberTwo = Employee::factory()->create();
    $outsider = Employee::factory()->create();

    TeamMember::factory()->create(['team_id' => $teamOne->id, 'employee_id' => $memberOne->id]);
    TeamMember::factory()->create(['team_id' => $teamTwo->id, 'employee_id' => $memberTwo->id]);
    TeamMember::factory()->create(['team_id' => $teamElsewhere->id, 'employee_id' => $outsider->id]);

    grantPermission($user, PermissionName::AttendanceView, Scope::Department, $department->id);

    $ids = app(ScopeResolver::class)->employeeIdsFor($user, PermissionName::AttendanceView);

    expect($ids)->toEqualCanonicalizing([$memberOne->id, $memberTwo->id]);
});

test('OPERATION resolves the same as DEPARTMENT for the assigned department', function () {
    $user = User::factory()->create();
    $department = Department::factory()->create();
    $team = Team::factory()->create(['department_id' => $department->id]);
    $member = Employee::factory()->create();
    TeamMember::factory()->create(['team_id' => $team->id, 'employee_id' => $member->id]);

    grantPermission($user, PermissionName::AttendanceView, Scope::Operation, $department->id);

    $ids = app(ScopeResolver::class)->employeeIdsFor($user, PermissionName::AttendanceView);

    expect($ids)->toBe([$member->id]);
});

test('multiple grants for the same permission are merged', function () {
    $user = User::factory()->create();
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();
    $memberA = Employee::factory()->create();
    $memberB = Employee::factory()->create();
    TeamMember::factory()->create(['team_id' => $teamA->id, 'employee_id' => $memberA->id]);
    TeamMember::factory()->create(['team_id' => $teamB->id, 'employee_id' => $memberB->id]);

    grantPermission($user, PermissionName::AttendanceView, Scope::Team, $teamA->id);
    grantPermission($user, PermissionName::AttendanceView, Scope::Team, $teamB->id);

    $ids = app(ScopeResolver::class)->employeeIdsFor($user, PermissionName::AttendanceView);

    expect($ids)->toEqualCanonicalizing([$memberA->id, $memberB->id]);
});

test('a grant for a different permission does not leak into this ones resolution', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $member = Employee::factory()->create();
    TeamMember::factory()->create(['team_id' => $team->id, 'employee_id' => $member->id]);

    grantPermission($user, PermissionName::LeaveApprove, Scope::Team, $team->id);

    $ids = app(ScopeResolver::class)->employeeIdsFor($user, PermissionName::AttendanceView);

    expect($ids)->toBe([]);
});
