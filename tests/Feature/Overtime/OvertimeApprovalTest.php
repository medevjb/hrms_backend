<?php

use App\Enums\OvertimeStatus;
use App\Enums\Scope;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\OrganizationSettings;
use App\Models\OvertimeRecord;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\UserRole;
use App\Services\OvertimeService;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * docs/PRD.md §50/§51/§67/§117/§121 — the overtime approval chain and the
 * approved-day total Phase 8 payroll will multiply by daily salary.
 */
function otGrantRole(User $user, string $roleName, array $permissionNames): void
{
    $role = Role::query()->firstOrCreate(['name' => $roleName]);

    foreach ($permissionNames as $permissionName) {
        $permission = Permission::query()->firstOrCreate(['name' => $permissionName]);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
    }

    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id, 'scope' => Scope::AllEmployees]);
}

/**
 * @return array{member: Employee, teamLeader: User, operationManager: User, hr: User}
 */
function otOrgChain(): array
{
    $omEmployee = Employee::factory()->create();
    $department = Department::factory()->create(['operation_manager_id' => $omEmployee->id]);

    $tlEmployee = Employee::factory()->create();
    $team = Team::factory()->create(['department_id' => $department->id, 'team_leader_id' => $tlEmployee->id]);

    $member = Employee::factory()->create(['overtime_eligible' => true]);
    TeamMember::factory()->create(['team_id' => $team->id, 'employee_id' => $member->id]);

    otGrantRole($tlEmployee->user, 'Team Leader', ['overtime.review', 'overtime.approve']);
    otGrantRole($omEmployee->user, 'Operation Manager', ['overtime.review', 'overtime.approve']);

    $hr = User::factory()->create();
    otGrantRole($hr, 'HR', ['overtime.review', 'overtime.approve']);

    return ['member' => $member, 'teamLeader' => $tlEmployee->user, 'operationManager' => $omEmployee->user, 'hr' => $hr];
}

function detectedOvertime(Employee $employee, string $date = '2026-08-22', int $worked = 510): OvertimeRecord
{
    $checkIn = Carbon::parse("{$date} 09:00:00");
    $attendance = AttendanceRecord::factory()->create([
        'employee_id' => $employee->id,
        'work_date' => $date,
        'check_in' => $checkIn,
        'check_out' => $checkIn->copy()->addMinutes($worked),
        'worked_minutes' => $worked,
    ]);

    app(OvertimeService::class)->detectForWorkDate(Carbon::parse($date));

    return OvertimeRecord::query()->where('attendance_record_id', $attendance->id)->sole();
}

beforeEach(function () {
    OrganizationSettings::current()->update([
        'timezone' => 'UTC',
        'weekend_days' => ['saturday', 'sunday'],
        'overtime_enabled' => true,
        'weekend_overtime_enabled' => true,
        'holiday_overtime_enabled' => true,
        'overtime_full_day_minutes' => 480,
    ]);
});

test('the full chain team leader then operation manager then HR approves the overtime', function () {
    $chain = otOrgChain();
    $record = detectedOvertime($chain['member']);

    $this->actingAs($chain['teamLeader'])->postJson("/api/v1/overtime/{$record->id}/approve")
        ->assertOk()->assertJsonPath('data.status', 'PENDING_OPERATION_MANAGER');

    $this->actingAs($chain['operationManager'])->postJson("/api/v1/overtime/{$record->id}/approve")
        ->assertOk()->assertJsonPath('data.status', 'PENDING_HR');

    $this->actingAs($chain['hr'])->postJson("/api/v1/overtime/{$record->id}/approve")
        ->assertOk()->assertJsonPath('data.status', 'APPROVED')
        ->assertJsonPath('data.current_stage', null);

    expect($record->fresh()->approvals)->toHaveCount(3);
});

test('Head of HR can approve outright from the first stage', function () {
    $chain = otOrgChain();
    $record = detectedOvertime($chain['member']);

    $headHr = User::factory()->create();
    otGrantRole($headHr, 'Head of HR', ['overtime.review', 'overtime.approve']);

    $this->actingAs($headHr)->postJson("/api/v1/overtime/{$record->id}/approve", ['reason' => 'Signed off directly'])
        ->assertOk()->assertJsonPath('data.status', 'APPROVED');

    expect($record->fresh()->approvals)->toHaveCount(1);
});

test('a team leader who does not lead this employee cannot approve', function () {
    $chain = otOrgChain();
    $record = detectedOvertime($chain['member']);

    $strangerTl = Employee::factory()->create();
    otGrantRole($strangerTl->user, 'Team Leader', ['overtime.approve']);

    $this->actingAs($strangerTl->user)->postJson("/api/v1/overtime/{$record->id}/approve")->assertStatus(403);
});

test('rejecting requires a reason and ends the chain', function () {
    $chain = otOrgChain();
    $record = detectedOvertime($chain['member']);

    $this->actingAs($chain['teamLeader'])->postJson("/api/v1/overtime/{$record->id}/reject", [])
        ->assertStatus(422);

    $this->actingAs($chain['teamLeader'])->postJson("/api/v1/overtime/{$record->id}/reject", ['reason' => 'Not authorised'])
        ->assertOk()->assertJsonPath('data.status', 'REJECTED');
});

test('a record with no open stage can no longer be approved', function () {
    $chain = otOrgChain();
    $record = detectedOvertime($chain['member']);
    $record->update(['status' => OvertimeStatus::Approved, 'current_stage' => null, 'decided_at' => now()]);

    // The policy gates a decided record out before the service's own 409
    // guard is reached — mirrors LeaveRequestPolicy.
    $this->actingAs($chain['hr'])->postJson("/api/v1/overtime/{$record->id}/approve")->assertStatus(403);
});

test('the service itself rejects a second decision with a 409', function () {
    $chain = otOrgChain();
    $record = detectedOvertime($chain['member']);
    $record->update(['status' => OvertimeStatus::Approved, 'current_stage' => null, 'decided_at' => now()]);

    expect(fn () => app(OvertimeService::class)->approve($record, $chain['hr']))
        ->toThrow(HttpException::class);
});

test('approvedOvertimeDaysFor sums an approved weekend and holiday day to two', function () {
    $chain = otOrgChain();
    $member = $chain['member'];

    $weekend = detectedOvertime($member, '2026-08-22');
    Holiday::factory()->create(['date' => '2026-08-25', 'active' => true]);
    $holiday = detectedOvertime($member, '2026-08-25');

    foreach ([$weekend, $holiday] as $record) {
        $record->update(['status' => OvertimeStatus::Approved, 'current_stage' => null, 'decided_at' => now()]);
    }

    $days = app(OvertimeService::class)->approvedOvertimeDaysFor(
        $member,
        Carbon::parse('2026-08-01'),
        Carbon::parse('2026-08-31'),
    );

    expect($days)->toBe(2.0);
});
