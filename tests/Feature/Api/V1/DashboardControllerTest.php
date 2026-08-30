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
