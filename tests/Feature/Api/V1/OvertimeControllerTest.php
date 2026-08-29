<?php

use App\Enums\OvertimeApprovalStage;
use App\Enums\OvertimeStatus;
use App\Enums\Scope;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\OrganizationSettings;
use App\Models\OvertimeRecord;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Carbon;

/**
 * docs/PRD.md §90 — overtime list/detail scoping and the §68 manual
 * adjustment endpoint. Chain approval itself is covered in
 * tests/Feature/Overtime/OvertimeApprovalTest.php.
 */
function octlGrantRole(User $user, string $roleName, array $permissionNames, Scope $scope = Scope::AllEmployees, ?int $scopeId = null): void
{
    $role = Role::query()->firstOrCreate(['name' => $roleName]);

    foreach ($permissionNames as $permissionName) {
        $permission = Permission::query()->firstOrCreate(['name' => $permissionName]);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
    }

    UserRole::factory()->create([
        'user_id' => $user->id, 'role_id' => $role->id, 'scope' => $scope, 'scope_id' => $scopeId,
    ]);
}

function octlOvertimeFor(Employee $employee, string $date, OvertimeStatus $status = OvertimeStatus::PendingTeamLeader): OvertimeRecord
{
    $checkIn = Carbon::parse("{$date} 09:00:00");
    $attendance = AttendanceRecord::factory()->create([
        'employee_id' => $employee->id,
        'work_date' => $date,
        'check_in' => $checkIn,
        'check_out' => $checkIn->copy()->addMinutes(510),
        'worked_minutes' => 510,
    ]);

    $stage = match ($status) {
        OvertimeStatus::PendingTeamLeader => OvertimeApprovalStage::TeamLeader,
        OvertimeStatus::PendingOperationManager => OvertimeApprovalStage::OperationManager,
        OvertimeStatus::PendingHr => OvertimeApprovalStage::Hr,
        default => null,
    };

    return OvertimeRecord::factory()->create([
        'employee_id' => $employee->id,
        'attendance_record_id' => $attendance->id,
        'work_date' => $date,
        'status' => $status,
        'current_stage' => $stage,
    ]);
}

beforeEach(function () {
    OrganizationSettings::current()->update(['timezone' => 'UTC']);
});

test('overtime index requires authentication', function () {
    $this->getJson('/api/v1/overtime')->assertStatus(401);
});

test('an employee sees their own overtime under filter[mine]', function () {
    $employee = Employee::factory()->create();
    octlGrantRole($employee->user, 'Team Member', ['overtime.view']);
    octlOvertimeFor($employee, '2026-08-22');
    octlOvertimeFor(Employee::factory()->create(), '2026-08-22');

    $response = $this->actingAs($employee->user)->getJson('/api/v1/overtime?filter[mine]=1');

    $response->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.employee.id', $employee->id);
});

test('a team leader scoped to their team only sees that team\'s overtime', function () {
    $tlEmployee = Employee::factory()->create();
    $team = Team::factory()->create(['team_leader_id' => $tlEmployee->id]);
    $member = Employee::factory()->create();
    TeamMember::factory()->create(['team_id' => $team->id, 'employee_id' => $member->id]);
    octlGrantRole($tlEmployee->user, 'Team Leader', ['overtime.view', 'overtime.review'], Scope::Team, $team->id);

    octlOvertimeFor($member, '2026-08-22');
    octlOvertimeFor(Employee::factory()->create(), '2026-08-22'); // another team

    $this->actingAs($tlEmployee->user)->getJson('/api/v1/overtime')
        ->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.employee.id', $member->id);
});

test('filter[pending_my_approval] returns only records at a stage this approver owns', function () {
    $tlEmployee = Employee::factory()->create();
    $team = Team::factory()->create(['team_leader_id' => $tlEmployee->id]);
    $member = Employee::factory()->create();
    TeamMember::factory()->create(['team_id' => $team->id, 'employee_id' => $member->id]);
    octlGrantRole($tlEmployee->user, 'Team Leader', ['overtime.review', 'overtime.approve']);

    $mine = octlOvertimeFor($member, '2026-08-22'); // PENDING_TEAM_LEADER, this team
    octlOvertimeFor($member, '2026-08-23', OvertimeStatus::PendingHr); // not my stage
    octlOvertimeFor(Employee::factory()->create(), '2026-08-22'); // another team

    $response = $this->actingAs($tlEmployee->user)->getJson('/api/v1/overtime?filter[pending_my_approval]=1');

    $response->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id);
});

test('show 404s for an overtime record outside the caller\'s scope', function () {
    $stranger = Employee::factory()->create();
    octlGrantRole($stranger->user, 'Team Member', ['overtime.view']);
    $record = octlOvertimeFor(Employee::factory()->create(), '2026-08-22');

    $this->actingAs($stranger->user)->getJson("/api/v1/overtime/{$record->id}")->assertStatus(404);
});

test('an authorised HR user grants a sub-threshold day and it becomes approved', function () {
    $headHr = User::factory()->create();
    octlGrantRole($headHr, 'Head of HR', ['overtime.adjust']);
    $record = octlOvertimeFor(Employee::factory()->create(), '2026-08-22', OvertimeStatus::Rejected);
    $record->update(['overtime_days' => 0, 'rejection_reason' => 'Insufficient working duration (200m of 480m required).']);

    $response = $this->actingAs($headHr)->patchJson("/api/v1/overtime/{$record->id}/adjust", [
        'overtime_days' => 1,
        'reason' => 'Client-critical release, approved by Head of HR',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'APPROVED')
        ->assertJsonPath('data.effective_overtime_days', 1)
        ->assertJsonPath('data.manual_days_override', 1);
});

test('adjust is forbidden without overtime.adjust', function () {
    $hr = User::factory()->create();
    octlGrantRole($hr, 'HR', ['overtime.review', 'overtime.approve']);
    $record = octlOvertimeFor(Employee::factory()->create(), '2026-08-22');

    $this->actingAs($hr)->patchJson("/api/v1/overtime/{$record->id}/adjust", [
        'overtime_days' => 1,
        'reason' => 'trying to adjust',
    ])->assertStatus(403);
});

test('adjust requires a reason', function () {
    $headHr = User::factory()->create();
    octlGrantRole($headHr, 'Head of HR', ['overtime.adjust']);
    $record = octlOvertimeFor(Employee::factory()->create(), '2026-08-22');

    $this->actingAs($headHr)->patchJson("/api/v1/overtime/{$record->id}/adjust", ['overtime_days' => 0])
        ->assertStatus(422);
});
