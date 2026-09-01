<?php

use App\Enums\AttendanceStatus;
use App\Enums\LeaveStatus;
use App\Enums\PermissionName;
use App\Enums\Scope;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OrganizationSettings;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Carbon;

/**
 * docs/PRD.md §73–§78 — the role-aware dashboard payload: widgets appear
 * only when the caller's permissions warrant them.
 */
function dashGrant(User $user, array $permissions, string $roleName = 'Dash', Scope $scope = Scope::AllEmployees): void
{
    $role = Role::query()->firstOrCreate(['name' => $roleName === 'Dash' ? 'Dash '.fake()->unique()->word() : $roleName]);

    foreach ($permissions as $permission) {
        $perm = Permission::query()->firstOrCreate(['name' => $permission]);
        $role->permissions()->syncWithoutDetaching([$perm->id]);
    }

    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id, 'scope' => $scope]);
}

beforeEach(fn () => OrganizationSettings::current()->update(['timezone' => 'UTC']));

test('the dashboard requires authentication', function () {
    $this->getJson('/api/v1/dashboard')->assertStatus(401);
});

test('a plain employee gets the "me" widget and nothing they cannot act on', function () {
    $employee = Employee::factory()->create();
    AttendanceRecord::factory()->create([
        'employee_id' => $employee->id,
        'work_date' => Carbon::now()->toDateString(),
        'status' => AttendanceStatus::Present,
    ]);

    $response = $this->actingAs($employee->user)->getJson('/api/v1/dashboard');

    $response->assertOk()
        ->assertJsonPath('data.widgets.me.today.status', 'PRESENT')
        ->assertJsonMissingPath('data.widgets.workforce')
        ->assertJsonMissingPath('data.widgets.pending_approvals');
});

test('an employee.view holder gets the workforce widget with real counts', function () {
    Employee::factory()->count(3)->create();
    Department::factory()->create();
    $user = User::factory()->create();
    dashGrant($user, [PermissionName::EmployeeView->value]);

    $this->actingAs($user)->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.widgets.workforce.total', 3)
        ->assertJsonPath('data.widgets.workforce.departments', 1);
});

test('a leave approver sees a pending-approval count for their own queue', function () {
    $omEmployee = Employee::factory()->create();
    $department = Department::factory()->create(['operation_manager_id' => $omEmployee->id]);
    $team = Team::factory()->create(['department_id' => $department->id]);
    $member = Employee::factory()->create();
    TeamMember::factory()->create(['team_id' => $team->id, 'employee_id' => $member->id]);

    $leaveType = LeaveType::factory()->create();
    LeaveRequest::factory()->create([
        'employee_id' => $member->id,
        'leave_type_id' => $leaveType->id,
        'status' => LeaveStatus::TeamLeaderApproved,
        'current_stage' => 'OPERATION_MANAGER',
        'required_stages' => ['TEAM_LEADER', 'OPERATION_MANAGER', 'HR'],
    ]);

    dashGrant($omEmployee->user, [PermissionName::LeaveApprove->value], 'Operation Manager');

    $this->actingAs($omEmployee->user)->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.widgets.pending_approvals.leave', 1);
});

test('attendance-today counts are scoped to what the caller can see', function () {
    $today = Carbon::now()->toDateString();
    $team = Team::factory()->create();
    $tl = Employee::factory()->create();
    $team->update(['team_leader_id' => $tl->id]);
    $mine = Employee::factory()->create();
    TeamMember::factory()->create(['team_id' => $team->id, 'employee_id' => $mine->id]);
    $other = Employee::factory()->create();

    AttendanceRecord::factory()->create(['employee_id' => $mine->id, 'work_date' => $today, 'status' => AttendanceStatus::Late]);
    AttendanceRecord::factory()->create(['employee_id' => $other->id, 'work_date' => $today, 'status' => AttendanceStatus::Late]);

    dashGrant($tl->user, [PermissionName::AttendanceView->value], 'Team Leader', Scope::Team);
    // point the TEAM-scoped grant at this team
    UserRole::query()->where('user_id', $tl->user->id)->update(['scope_id' => $team->id]);

    $this->actingAs($tl->user)->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.widgets.attendance_today.late', 1);
});

test('the "me" widget reports the next approved leave, or null when there is none', function () {
    $employee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create(['name' => 'Casual Leave']);

    $this->actingAs($employee->user)->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.widgets.me.next_approved_leave', null);

    LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'status' => LeaveStatus::HrApproved,
        'current_stage' => null,
        'start_date' => Carbon::now()->addDays(3)->toDateString(),
        'end_date' => Carbon::now()->addDays(4)->toDateString(),
        'days_requested' => 2,
    ]);

    $this->actingAs($employee->user)->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.widgets.me.next_approved_leave.leave_type', 'Casual Leave')
        ->assertJsonPath('data.widgets.me.next_approved_leave.days_requested', 2);
});

test('the "me" widget reports the effective weekend days — the employee override wins', function () {
    $orgFollower = Employee::factory()->create(['weekend_day' => null]);
    $this->actingAs($orgFollower->user)->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.widgets.me.weekend_days', ['friday']);

    $withOverride = Employee::factory()->create(['weekend_day' => 'sunday']);
    $this->actingAs($withOverride->user)->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.widgets.me.weekend_days', ['sunday']);
});

test('a leave balance with nothing taken reads back fully available', function () {
    $employee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create(['annual_allocation_days' => 15]);
    // A prorated mid-year opening — 11 accrued, none used.
    $balance = $employee->leaveBalances()->create([
        'leave_type_id' => $leaveType->id,
        'leave_year' => Carbon::now()->year,
        'balance' => 11,
    ]);
    $balance->transactions()->create(['type' => 'ACCRUAL', 'amount' => 11, 'note' => 'opening']);

    $this->actingAs($employee->user)->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.widgets.me.leave_balances.0.balance', 11)
        ->assertJsonPath('data.widgets.me.leave_balances.0.taken', 0)
        ->assertJsonPath('data.widgets.me.leave_balances.0.entitlement', 11);
});

test('taken reflects approved leave, net of cancellations', function () {
    $employee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create(['annual_allocation_days' => 15]);
    $balance = $employee->leaveBalances()->create([
        'leave_type_id' => $leaveType->id,
        'leave_year' => Carbon::now()->year,
        'balance' => 12,
    ]);
    $balance->transactions()->createMany([
        ['type' => 'ACCRUAL', 'amount' => 15, 'note' => 'opening'],
        ['type' => 'APPROVAL', 'amount' => -4],
        ['type' => 'CANCELLATION', 'amount' => 1],
    ]);

    $this->actingAs($employee->user)->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.widgets.me.leave_balances.0.balance', 12)
        ->assertJsonPath('data.widgets.me.leave_balances.0.taken', 3)
        ->assertJsonPath('data.widgets.me.leave_balances.0.entitlement', 15);
});

test('the dashboard leave balances skip inactive types and other years', function () {
    $employee = Employee::factory()->create();
    $active = LeaveType::factory()->create(['annual_allocation_days' => 10, 'is_active' => true]);
    $inactive = LeaveType::factory()->create(['annual_allocation_days' => 10, 'is_active' => false]);

    $employee->leaveBalances()->create([
        'leave_type_id' => $active->id, 'leave_year' => Carbon::now()->year, 'balance' => 8,
    ]);
    $employee->leaveBalances()->create([
        'leave_type_id' => $inactive->id, 'leave_year' => Carbon::now()->year, 'balance' => 5,
    ]);
    $employee->leaveBalances()->create([
        'leave_type_id' => $active->id, 'leave_year' => Carbon::now()->year - 1, 'balance' => 3,
    ]);

    $this->actingAs($employee->user)->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonCount(1, 'data.widgets.me.leave_balances')
        ->assertJsonPath('data.widgets.me.leave_balances.0.balance', 8);
});

test('the workforce widget breaks headcount down by department', function () {
    $engineering = Department::factory()->create(['name' => 'Engineering']);
    $team = Team::factory()->create(['department_id' => $engineering->id]);
    $member = Employee::factory()->create();
    TeamMember::factory()->create(['team_id' => $team->id, 'employee_id' => $member->id]);

    $user = User::factory()->create();
    dashGrant($user, [PermissionName::EmployeeView->value]);

    $response = $this->actingAs($user)->getJson('/api/v1/dashboard')->assertOk();

    $engineeringRow = collect($response->json('data.widgets.workforce.by_department'))
        ->firstWhere('name', 'Engineering');

    expect($engineeringRow['headcount'])->toBe(1);
});

test('on-leave-today is scoped for a Team Leader and unrestricted for an all-employees grant', function () {
    $today = Carbon::now()->toDateString();
    $leaveType = LeaveType::factory()->create(['name' => 'Sick Leave']);

    $team = Team::factory()->create();
    $tl = Employee::factory()->create();
    $team->update(['team_leader_id' => $tl->id]);
    $mine = Employee::factory()->create();
    TeamMember::factory()->create(['team_id' => $team->id, 'employee_id' => $mine->id]);
    $other = Employee::factory()->create();

    foreach ([$mine, $other] as $onLeave) {
        LeaveRequest::factory()->create([
            'employee_id' => $onLeave->id,
            'leave_type_id' => $leaveType->id,
            'status' => LeaveStatus::HrApproved,
            'current_stage' => null,
            'start_date' => $today,
            'end_date' => $today,
        ]);
    }

    dashGrant($tl->user, [PermissionName::AttendanceView->value], 'Team Leader', Scope::Team);
    UserRole::query()->where('user_id', $tl->user->id)->update(['scope_id' => $team->id]);

    $this->actingAs($tl->user)->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.widgets.attendance_today.on_leave_today', [[
            'employee_id' => $mine->id,
            'name' => $mine->fullName(),
            'leave_type' => 'Sick Leave',
            'until' => $today,
        ]]);

    $hr = User::factory()->create();
    dashGrant($hr, [PermissionName::AttendanceView->value]);

    $this->actingAs($hr)->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonCount(2, 'data.widgets.attendance_today.on_leave_today');
});

test('people_movement is only present with employee.view and is scoped', function () {
    $recentJoiner = Employee::factory()->create(['joining_date' => Carbon::now()->subDays(5)->toDateString()]);
    Employee::factory()->create(['joining_date' => Carbon::now()->subMonths(6)->toDateString()]);

    $plain = Employee::factory()->create();
    $this->actingAs($plain->user)->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonMissingPath('data.widgets.people_movement');

    $user = User::factory()->create();
    dashGrant($user, [PermissionName::EmployeeView->value]);

    $response = $this->actingAs($user)->getJson('/api/v1/dashboard')->assertOk();

    $joinerIds = collect($response->json('data.widgets.people_movement.recent_joiners'))->pluck('employee_id');
    expect($joinerIds)->toContain($recentJoiner->id)
        ->and($joinerIds)->toHaveCount(1);
});

test('a Team Member can read their own attendance month without a wider grant', function () {
    $employee = Employee::factory()->create();
    $role = Role::query()->firstOrCreate(['name' => 'Team Member']);
    $perm = Permission::query()->firstOrCreate(['name' => PermissionName::AttendanceView->value]);
    $role->permissions()->syncWithoutDetaching([$perm->id]);
    UserRole::factory()->create(['user_id' => $employee->user->id, 'role_id' => $role->id, 'scope' => Scope::Self]);

    AttendanceRecord::factory()->create([
        'employee_id' => $employee->id,
        'work_date' => Carbon::now()->toDateString(),
        'status' => AttendanceStatus::Present,
    ]);

    $this->actingAs($employee->user)
        ->getJson('/api/v1/attendance?filter[employee_id]='.$employee->id)
        ->assertOk()
        ->assertJsonPath('data.0.employee.id', $employee->id);
});
