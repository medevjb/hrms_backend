<?php

use App\Enums\Scope;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\UserRole;
use App\Services\LeaveBalanceService;
use Illuminate\Support\Carbon;

/**
 * @return array{teamMember: Employee, teamLeader: Employee, operationManager: Employee, hr: User}
 */
function httpLeaveOrgChain(): array
{
    $veteranJoiningDate = '2020-01-01';

    $operationManagerEmployee = Employee::factory()->create(['joining_date' => $veteranJoiningDate]);
    $department = Department::factory()->create(['operation_manager_id' => $operationManagerEmployee->id]);

    $teamLeaderEmployee = Employee::factory()->create(['joining_date' => $veteranJoiningDate]);
    $team = Team::factory()->create(['department_id' => $department->id, 'team_leader_id' => $teamLeaderEmployee->id]);

    $teamMember = Employee::factory()->create(['joining_date' => $veteranJoiningDate]);
    TeamMember::factory()->create(['team_id' => $team->id, 'employee_id' => $teamMember->id]);
    TeamMember::factory()->create(['team_id' => $team->id, 'employee_id' => $teamLeaderEmployee->id]);

    httpGrantRole($teamMember->user, 'Team Member', ['leave.request']);
    httpGrantRole($teamLeaderEmployee->user, 'Team Leader', ['leave.request', 'leave.approve']);
    httpGrantRole($operationManagerEmployee->user, 'Operation Manager', ['leave.approve']);

    $hr = User::factory()->create();
    httpGrantRole($hr, 'HR', ['leave.approve', 'leave.review']);

    return [
        'teamMember' => $teamMember,
        'teamLeader' => $teamLeaderEmployee,
        'operationManager' => $operationManagerEmployee,
        'hr' => $hr,
    ];
}

function httpGrantRole(User $user, string $roleName, array $permissionNames): void
{
    $role = Role::query()->firstOrCreate(['name' => $roleName]);

    foreach ($permissionNames as $permissionName) {
        $permission = Permission::query()->firstOrCreate(['name' => $permissionName]);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
    }

    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id, 'scope' => Scope::AllEmployees]);
}

beforeEach(function () {
    Carbon::setTestNow('2026-08-24 09:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

test('POST leave-requests requires authentication', function () {
    $this->postJson('/api/v1/leave-requests', [])->assertStatus(401);
});

test('an employee can submit a leave request and it lands on their team leader\'s stage', function () {
    $chain = httpLeaveOrgChain();
    $leaveType = LeaveType::factory()->create(['annual_allocation_days' => 15]);
    app(LeaveBalanceService::class)->balanceFor($chain['teamMember'], $leaveType, 2026);

    $response = $this->actingAs($chain['teamMember']->user)->postJson('/api/v1/leave-requests', [
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-08-25',
        'end_date' => '2026-08-26',
        'reason' => 'Personal matter',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.status', 'SUBMITTED');
    $response->assertJsonPath('data.current_stage', 'TEAM_LEADER');
});

test('a Team Leader who is not this employee\'s team leader cannot approve their request', function () {
    $chain = httpLeaveOrgChain();
    $otherTeamLeader = Employee::factory()->create();
    httpGrantRole($otherTeamLeader->user, 'Team Leader', ['leave.approve']);

    $leaveType = LeaveType::factory()->create(['annual_allocation_days' => 15]);
    app(LeaveBalanceService::class)->balanceFor($chain['teamMember'], $leaveType, 2026);

    $submit = $this->actingAs($chain['teamMember']->user)->postJson('/api/v1/leave-requests', [
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-08-25',
        'end_date' => '2026-08-25',
    ]);
    $requestId = $submit->json('data.id');

    $this->actingAs($otherTeamLeader->user)
        ->postJson("/api/v1/leave-requests/{$requestId}/approve")
        ->assertStatus(403);
});

test('the assigned team leader can approve, moving the request to the operation manager stage', function () {
    $chain = httpLeaveOrgChain();
    $leaveType = LeaveType::factory()->create(['annual_allocation_days' => 15]);
    app(LeaveBalanceService::class)->balanceFor($chain['teamMember'], $leaveType, 2026);

    $submit = $this->actingAs($chain['teamMember']->user)->postJson('/api/v1/leave-requests', [
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-08-25',
        'end_date' => '2026-08-25',
    ]);
    $requestId = $submit->json('data.id');

    $response = $this->actingAs($chain['teamLeader']->user)->postJson("/api/v1/leave-requests/{$requestId}/approve");

    $response->assertOk();
    $response->assertJsonPath('data.status', 'TEAM_LEADER_APPROVED');
    $response->assertJsonPath('data.current_stage', 'OPERATION_MANAGER');
});

test('rejecting requires a reason', function () {
    $chain = httpLeaveOrgChain();
    $leaveType = LeaveType::factory()->create(['annual_allocation_days' => 15]);
    app(LeaveBalanceService::class)->balanceFor($chain['teamMember'], $leaveType, 2026);

    $submit = $this->actingAs($chain['teamMember']->user)->postJson('/api/v1/leave-requests', [
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-08-25',
        'end_date' => '2026-08-25',
    ]);
    $requestId = $submit->json('data.id');

    $this->actingAs($chain['teamLeader']->user)
        ->postJson("/api/v1/leave-requests/{$requestId}/reject", [])
        ->assertStatus(422);

    $this->actingAs($chain['teamLeader']->user)
        ->postJson("/api/v1/leave-requests/{$requestId}/reject", ['reason' => 'Team is short-staffed'])
        ->assertOk()
        ->assertJsonPath('data.status', 'REJECTED');
});

test('an employee can view their own request but not someone else\'s', function () {
    $chain = httpLeaveOrgChain();
    $stranger = Employee::factory()->create();
    httpGrantRole($stranger->user, 'Team Member', ['leave.request']);

    $leaveType = LeaveType::factory()->create(['annual_allocation_days' => 15]);
    app(LeaveBalanceService::class)->balanceFor($chain['teamMember'], $leaveType, 2026);

    $submit = $this->actingAs($chain['teamMember']->user)->postJson('/api/v1/leave-requests', [
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-08-25',
        'end_date' => '2026-08-25',
    ]);
    $requestId = $submit->json('data.id');

    $this->actingAs($chain['teamMember']->user)->getJson("/api/v1/leave-requests/{$requestId}")->assertOk();
    $this->actingAs($stranger->user)->getJson("/api/v1/leave-requests/{$requestId}")->assertStatus(404);
});

test('the employee can cancel their own approved request and the balance is refunded', function () {
    $chain = httpLeaveOrgChain();
    $leaveType = LeaveType::factory()->create(['annual_allocation_days' => 15]);
    $balance = app(LeaveBalanceService::class)->balanceFor($chain['teamMember'], $leaveType, 2026);

    $submit = $this->actingAs($chain['teamMember']->user)->postJson('/api/v1/leave-requests', [
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-08-25',
        'end_date' => '2026-08-25',
    ]);
    $requestId = $submit->json('data.id');

    $this->actingAs($chain['teamLeader']->user)->postJson("/api/v1/leave-requests/{$requestId}/approve")->assertOk();
    $this->actingAs($chain['operationManager']->user)->postJson("/api/v1/leave-requests/{$requestId}/approve")->assertOk();
    $this->actingAs($chain['hr'])->postJson("/api/v1/leave-requests/{$requestId}/approve")->assertOk();

    expect((float) $balance->fresh()->balance)->toBe(14.0);

    $this->actingAs($chain['teamMember']->user)
        ->postJson("/api/v1/leave-requests/{$requestId}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'CANCELLED');

    expect((float) $balance->fresh()->balance)->toBe(15.0);
});
