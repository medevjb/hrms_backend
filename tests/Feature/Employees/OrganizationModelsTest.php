<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;

test('an employee derives their current team, team leader, and operation manager', function () {
    $operationManager = Employee::factory()->create();
    $department = Department::factory()->create(['operation_manager_id' => $operationManager->id]);
    $teamLeader = Employee::factory()->create();
    $team = Team::factory()->create(['department_id' => $department->id, 'team_leader_id' => $teamLeader->id]);
    $employee = Employee::factory()->create();
    TeamMember::factory()->create(['team_id' => $team->id, 'employee_id' => $employee->id]);

    expect($employee->currentTeam()->id)->toBe($team->id);
    expect($employee->teamLeader()->id)->toBe($teamLeader->id);
    expect($employee->operationManager()->id)->toBe($operationManager->id);
});

test('an employee with no team membership has no derived team, leader, or manager', function () {
    $employee = Employee::factory()->create();

    expect($employee->currentTeam())->toBeNull();
    expect($employee->teamLeader())->toBeNull();
    expect($employee->operationManager())->toBeNull();
});

test('an ended team membership does not count as current', function () {
    $team = Team::factory()->create();
    $employee = Employee::factory()->create();
    TeamMember::factory()->ended()->create(['team_id' => $team->id, 'employee_id' => $employee->id]);

    expect($employee->fresh()->currentTeam())->toBeNull();
});

test('a transfer closes the old membership and opens a new one, preserving history', function () {
    $oldTeam = Team::factory()->create();
    $newTeam = Team::factory()->create();
    $employee = Employee::factory()->create();

    $oldMembership = TeamMember::factory()->create([
        'team_id' => $oldTeam->id,
        'employee_id' => $employee->id,
        'started_at' => '2026-01-01',
    ]);

    $oldMembership->update(['ended_at' => '2026-06-01']);
    TeamMember::factory()->create([
        'team_id' => $newTeam->id,
        'employee_id' => $employee->id,
        'started_at' => '2026-06-01',
    ]);

    expect($employee->fresh()->currentTeam()->id)->toBe($newTeam->id);
    expect($employee->teamMemberships()->count())->toBe(2);
    expect($oldMembership->fresh()->ended_at)->not->toBeNull();
});

test('a user is not required to have an employee record', function () {
    $user = User::factory()->create();

    expect($user->employee)->toBeNull();
});
