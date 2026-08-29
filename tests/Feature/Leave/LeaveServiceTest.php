<?php

use App\Enums\EmployeeStatus;
use App\Enums\HalfDayPeriod;
use App\Enums\LeaveStatus;
use App\Enums\Scope;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\OrganizationSettings;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\UserRole;
use App\Services\LeaveBalanceService;
use App\Services\LeaveService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Builds a full org chain — Team Member under a Team Leader under an
 * Operation Manager, plus standalone HR/Head HR/Admin users — mirroring
 * docs/PRD.md §41's routing table.
 *
 * @return array{teamMember: Employee, teamLeader: Employee, operationManager: Employee, hr: User, headHr: User, admin: User}
 */
function buildLeaveOrgChain(): array
{
    // Pinned well before the 2026 leave year — the factory's default
    // joining_date is randomized against real wall-clock "now" and would
    // otherwise sometimes fall inside the leave year under test, silently
    // prorating the opening allocation instead of crediting the full amount.
    $veteranJoiningDate = '2020-01-01';

    $operationManager = Employee::factory()->create(['joining_date' => $veteranJoiningDate]);
    $department = Department::factory()->create(['operation_manager_id' => $operationManager->id]);

    $teamLeaderEmployee = Employee::factory()->create(['joining_date' => $veteranJoiningDate]);
    $team = Team::factory()->create(['department_id' => $department->id, 'team_leader_id' => $teamLeaderEmployee->id]);

    $teamMember = Employee::factory()->create(['joining_date' => $veteranJoiningDate]);
    TeamMember::factory()->create(['team_id' => $team->id, 'employee_id' => $teamMember->id]);
    TeamMember::factory()->create(['team_id' => $team->id, 'employee_id' => $teamLeaderEmployee->id]);

    roleFor($teamMember->user, 'Team Member', []);
    roleFor($teamLeaderEmployee->user, 'Team Leader', ['leave.approve']);
    roleFor($operationManager->user, 'Operation Manager', ['leave.approve']);

    $hr = User::factory()->create();
    roleFor($hr, 'HR', ['leave.approve']);

    $headHr = User::factory()->create();
    roleFor($headHr, 'Head of HR', ['leave.approve', 'leave.override']);

    $admin = User::factory()->create();
    roleFor($admin, 'Admin', ['leave.approve', 'leave.override']);

    return [
        'teamMember' => $teamMember,
        'teamLeader' => $teamLeaderEmployee,
        'operationManager' => $operationManager,
        'hr' => $hr,
        'headHr' => $headHr,
        'admin' => $admin,
    ];
}

function roleFor(User $user, string $roleName, array $permissionNames): Role
{
    $role = Role::query()->firstOrCreate(['name' => $roleName]);

    foreach ($permissionNames as $permissionName) {
        $permission = Permission::query()->firstOrCreate(['name' => $permissionName]);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
    }

    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id, 'scope' => Scope::AllEmployees]);

    return $role;
}

beforeEach(function () {
    OrganizationSettings::current()->update(['timezone' => 'UTC', 'leave_year_start_month' => 1]);
    Carbon::setTestNow('2026-08-24 09:00:00'); // a Monday
});

afterEach(function () {
    Carbon::setTestNow();
});

test('a Team Member leave request routes through TL, OM, then HR and debits the balance only on final approval', function () {
    $chain = buildLeaveOrgChain();
    $leaveType = LeaveType::factory()->create(['annual_allocation_days' => 15, 'accrual_mode' => 'UPFRONT']);
    $balance = app(LeaveBalanceService::class)->balanceFor($chain['teamMember'], $leaveType, 2026);
    expect((float) $balance->balance)->toBe(15.0);

    $service = app(LeaveService::class);

    $request = $service->submit(
        $chain['teamMember'],
        $leaveType,
        Carbon::parse('2026-08-25'),
        Carbon::parse('2026-08-26'),
        false,
        null,
        'Family event',
    );

    expect($request->status)->toBe(LeaveStatus::Submitted);
    expect($request->current_stage->value)->toBe('TEAM_LEADER');
    expect((float) $request->days_requested)->toBe(2.0);

    $request = $service->approve($request, $chain['teamLeader']->user);
    expect($request->status)->toBe(LeaveStatus::TeamLeaderApproved);
    expect($request->current_stage->value)->toBe('OPERATION_MANAGER');

    $request = $service->approve($request, $chain['operationManager']->user);
    expect($request->status)->toBe(LeaveStatus::OperationManagerApproved);
    expect($request->current_stage->value)->toBe('HR');

    // balance untouched while still in flight
    expect((float) $balance->fresh()->balance)->toBe(15.0);

    $request = $service->approve($request, $chain['hr']);
    expect($request->status)->toBe(LeaveStatus::HrApproved);
    expect($request->current_stage)->toBeNull();
    expect($request->decided_at)->not->toBeNull();

    expect((float) $balance->fresh()->balance)->toBe(13.0);
});

test('rejection at any stage stops the chain and never touches the balance', function () {
    $chain = buildLeaveOrgChain();
    $leaveType = LeaveType::factory()->create(['annual_allocation_days' => 15]);
    $balance = app(LeaveBalanceService::class)->balanceFor($chain['teamMember'], $leaveType, 2026);

    $service = app(LeaveService::class);
    $request = $service->submit($chain['teamMember'], $leaveType, Carbon::parse('2026-08-25'), Carbon::parse('2026-08-25'), false, null, null);

    $request = $service->reject($request, $chain['teamLeader']->user, 'Short-staffed that week');

    expect($request->status)->toBe(LeaveStatus::Rejected);
    expect($request->current_stage)->toBeNull();
    expect($request->rejection_reason)->toBe('Short-staffed that week');
    expect((float) $balance->fresh()->balance)->toBe(15.0);
});

test('§40 direct approval by Head HR bypasses the remaining chain and records the bypassed stages', function () {
    $chain = buildLeaveOrgChain();
    $leaveType = LeaveType::factory()->create(['annual_allocation_days' => 15]);

    $service = app(LeaveService::class);
    $request = $service->submit($chain['teamMember'], $leaveType, Carbon::parse('2026-08-25'), Carbon::parse('2026-08-25'), false, null, null);

    $request = $service->directApprove($request, $chain['headHr'], 'Urgent family emergency, approving directly');

    expect($request->status)->toBe(LeaveStatus::HrApproved);
    expect($request->is_direct_approval)->toBeTrue();
    expect($request->bypassed_stages)->toBe(['TEAM_LEADER', 'OPERATION_MANAGER', 'HR']);
    expect($request->approvals()->count())->toBe(1);
});

test('an Operation Manager\'s own leave only needs Head HR (§41)', function () {
    $chain = buildLeaveOrgChain();
    $leaveType = LeaveType::factory()->create(['annual_allocation_days' => 15]);

    $request = app(LeaveService::class)->submit(
        $chain['operationManager'],
        $leaveType,
        Carbon::parse('2026-08-25'),
        Carbon::parse('2026-08-25'),
        false,
        null,
        null,
    );

    expect($request->required_stages)->toBe(['HEAD_HR']);
    expect($request->current_stage->value)->toBe('HEAD_HR');
});

test('cancelling an approved future leave refunds the balance', function () {
    $chain = buildLeaveOrgChain();
    $leaveType = LeaveType::factory()->create(['annual_allocation_days' => 15]);
    $balanceService = app(LeaveBalanceService::class);
    $balance = $balanceService->balanceFor($chain['teamMember'], $leaveType, 2026);

    $service = app(LeaveService::class);
    $request = $service->submit($chain['teamMember'], $leaveType, Carbon::parse('2026-08-25'), Carbon::parse('2026-08-26'), false, null, null);
    $request = $service->approve($request, $chain['teamLeader']->user);
    $request = $service->approve($request, $chain['operationManager']->user);
    $request = $service->approve($request, $chain['hr']);

    expect((float) $balance->fresh()->balance)->toBe(13.0);

    $request = $service->cancel($request, $chain['teamMember']->user);

    expect($request->status)->toBe(LeaveStatus::Cancelled);
    expect((float) $balance->fresh()->balance)->toBe(15.0);
});

test('half-day requests always cost exactly 0.5 days and require a matching start/end date', function () {
    $chain = buildLeaveOrgChain();
    $leaveType = LeaveType::factory()->create(['annual_allocation_days' => 15, 'supports_half_day' => true]);

    $request = app(LeaveService::class)->submit(
        $chain['teamMember'],
        $leaveType,
        Carbon::parse('2026-08-25'),
        Carbon::parse('2026-08-25'),
        true,
        HalfDayPeriod::FirstHalf,
        null,
    );

    expect((float) $request->days_requested)->toBe(0.5);
    expect($request->half_day_period)->toBe(HalfDayPeriod::FirstHalf);
});

test('submitting a request that exceeds the available balance is rejected', function () {
    $chain = buildLeaveOrgChain();
    $leaveType = LeaveType::factory()->create(['annual_allocation_days' => 2]);

    expect(fn () => app(LeaveService::class)->submit(
        $chain['teamMember'],
        $leaveType,
        Carbon::parse('2026-08-25'),
        Carbon::parse('2026-08-28'), // 4 working days, only 2 available
        false,
        null,
        null,
    ))->toThrow(ValidationException::class);
});

test('overlapping leave requests are rejected', function () {
    $chain = buildLeaveOrgChain();
    $leaveType = LeaveType::factory()->create(['annual_allocation_days' => 15]);
    $service = app(LeaveService::class);

    $service->submit($chain['teamMember'], $leaveType, Carbon::parse('2026-08-25'), Carbon::parse('2026-08-27'), false, null, null);

    expect(fn () => $service->submit(
        $chain['teamMember'],
        $leaveType,
        Carbon::parse('2026-08-26'),
        Carbon::parse('2026-08-29'),
        false,
        null,
        null,
    ))->toThrow(ValidationException::class);
});

test('a leave type with a minimum employment period blocks a too-recent joiner', function () {
    $employee = Employee::factory()->create(['joining_date' => Carbon::now()->subDays(10), 'status' => EmployeeStatus::Probation]);
    $leaveType = LeaveType::factory()->create(['annual_allocation_days' => 15, 'min_employment_days' => 90]);

    expect(fn () => app(LeaveService::class)->submit(
        $employee,
        $leaveType,
        Carbon::parse('2026-08-25'),
        Carbon::parse('2026-08-25'),
        false,
        null,
        null,
    ))->toThrow(ValidationException::class);
});
