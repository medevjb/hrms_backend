<?php

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\PermissionName;
use App\Enums\Scope;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\OrganizationSettings;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Shift;
use App\Models\Team;
use App\Models\User;
use App\Models\UserRole;
use App\Services\AttendanceService;
use Illuminate\Support\Carbon;

function userWithAttendancePermission(PermissionName $permission, Scope $scope = Scope::AllEmployees): User
{
    $user = User::factory()->create();
    $role = Role::factory()->create();
    $perm = Permission::query()->firstOrCreate(['name' => $permission->value]);
    $role->permissions()->attach($perm);
    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id, 'scope' => $scope]);

    return $user;
}

beforeEach(function () {
    OrganizationSettings::current()->update(['timezone' => 'UTC', 'late_grace_minutes' => 10]);
});

test('GET attendance/today requires authentication', function () {
    $this->getJson('/api/v1/attendance/today')->assertStatus(401);
});

test('GET attendance/today 404s for a user with no employee profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/attendance/today')->assertStatus(404);
});

test('GET attendance/today returns the resolved context for the caller\'s own employee', function () {
    $employee = Employee::factory()->create();
    $shift = Shift::factory()->create(['start_time' => '09:00:00', 'end_time' => '18:00:00']);
    EmployeeShift::factory()->create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'started_at' => '2026-01-01']);

    $response = $this->actingAs($employee->user)->getJson('/api/v1/attendance/today');

    $response->assertOk();
    $response->assertJsonPath('data.shift.id', $shift->id);
    $response->assertJsonPath('data.record', null);
});

test('checking in creates a record for the authenticated employee', function () {
    // Pinned inside the shift's check-in window — this endpoint reads the
    // real clock, and the window is only a few hours wide around the shift.
    Carbon::setTestNow('2026-08-24 09:05:00');
    $employee = Employee::factory()->create();
    $shift = Shift::factory()->create(['start_time' => '09:00:00', 'end_time' => '18:00:00']);
    EmployeeShift::factory()->create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'started_at' => '2026-01-01']);

    $response = $this->actingAs($employee->user)->postJson('/api/v1/attendance/check-in');

    $response->assertStatus(201);
    $response->assertJsonPath('data.employee.id', $employee->id);
    expect(AttendanceRecord::query()->where('employee_id', $employee->id)->exists())->toBeTrue();

    Carbon::setTestNow();
});

test('a duplicate check-in returns 409 with the existing record attached', function () {
    Carbon::setTestNow('2026-08-24 09:05:00');
    $employee = Employee::factory()->create();
    $shift = Shift::factory()->create(['start_time' => '09:00:00']);
    EmployeeShift::factory()->create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'started_at' => '2026-01-01']);

    $this->actingAs($employee->user)->postJson('/api/v1/attendance/check-in')->assertStatus(201);
    $response = $this->actingAs($employee->user)->postJson('/api/v1/attendance/check-in');

    $response->assertStatus(409);
    $response->assertJsonPath('code', 'ALREADY_CHECKED_IN');
    $response->assertJsonPath('data.employee.id', $employee->id);

    Carbon::setTestNow();
});

test('checking out without checking in returns 409', function () {
    $employee = Employee::factory()->create();

    $response = $this->actingAs($employee->user)->postJson('/api/v1/attendance/check-out');

    $response->assertStatus(409);
    $response->assertJsonPath('code', 'NOT_CHECKED_IN');
});

test('check-in then check-out succeeds and computes worked minutes', function () {
    Carbon::setTestNow('2026-08-24 09:05:00');
    $employee = Employee::factory()->create();
    $shift = Shift::factory()->create(['start_time' => '09:00:00']);
    EmployeeShift::factory()->create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'started_at' => '2026-01-01']);

    $this->actingAs($employee->user)->postJson('/api/v1/attendance/check-in')->assertStatus(201);
    $response = $this->actingAs($employee->user)->postJson('/api/v1/attendance/check-out');

    $response->assertOk();
    expect($response->json('data.worked_minutes'))->toBeInt();

    Carbon::setTestNow();
});

test('listing attendance requires attendance.view', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/attendance')->assertStatus(403);
});

test('a user with attendance.view sees records within their scope', function () {
    $employee = Employee::factory()->create();
    AttendanceRecord::factory()->create(['employee_id' => $employee->id, 'work_date' => '2026-08-24']);

    $user = userWithAttendancePermission(PermissionName::AttendanceView);

    $response = $this->actingAs($user)->getJson('/api/v1/attendance');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
});

test('filtering attendance by a reporting-period key', function () {
    OrganizationSettings::current()->update(['reporting_month_cutoff_day' => 25]);

    $employee = Employee::factory()->create();
    AttendanceRecord::factory()->create(['employee_id' => $employee->id, 'work_date' => '2026-08-28']); // in Sep period
    AttendanceRecord::factory()->create(['employee_id' => $employee->id, 'work_date' => '2026-08-20']); // in Aug period

    $user = userWithAttendancePermission(PermissionName::AttendanceView);

    $this->actingAs($user)->getJson('/api/v1/attendance?filter[period]=2026-09')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.work_date', '2026-08-28');
});

test('filtering attendance by status', function () {
    $employeeA = Employee::factory()->create();
    $employeeB = Employee::factory()->create();
    AttendanceRecord::factory()->create(['employee_id' => $employeeA->id, 'work_date' => '2026-08-24', 'status' => AttendanceStatus::Late]);
    AttendanceRecord::factory()->create(['employee_id' => $employeeB->id, 'work_date' => '2026-08-24', 'status' => AttendanceStatus::Present]);

    $user = userWithAttendancePermission(PermissionName::AttendanceView);

    $response = $this->actingAs($user)->getJson('/api/v1/attendance?filter[status]=LATE');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.employee.id', $employeeA->id);
});

test('adjusting attendance requires attendance.correct', function () {
    $employee = Employee::factory()->create();
    $record = AttendanceRecord::factory()->create(['employee_id' => $employee->id]);
    $user = userWithAttendancePermission(PermissionName::AttendanceView);

    $this->actingAs($user)->patchJson("/api/v1/attendance/{$record->id}/adjust", [
        'check_in' => '2026-08-24T09:05:00+00:00',
        'reason' => 'test',
    ])->assertStatus(404);
});

test('adjusting an out-of-scope record is 404', function () {
    $employee = Employee::factory()->create();
    $record = AttendanceRecord::factory()->create(['employee_id' => $employee->id]);
    $otherTeam = Team::factory()->create();
    $user = userWithAttendancePermission(PermissionName::AttendanceCorrect, Scope::Team);
    // narrow the grant to a team the employee is not on
    $user->roleAssignments()->update(['scope_id' => $otherTeam->id]);

    $this->actingAs($user)->patchJson("/api/v1/attendance/{$record->id}/adjust", [
        'check_in' => '2026-08-24T09:05:00+00:00',
        'reason' => 'test',
    ])->assertStatus(404);
});

test('a user with attendance.correct can adjust a record and it recalculates status', function () {
    $employee = Employee::factory()->create();
    $shift = Shift::factory()->create(['start_time' => '09:00:00', 'late_grace_minutes' => null]);
    EmployeeShift::factory()->create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'started_at' => '2026-01-01']);
    $actor = User::factory()->create();
    $record = app(AttendanceService::class)->checkIn($employee, $actor, AttendanceSource::Web, Carbon::parse('2026-08-24 09:15:00'));
    expect($record->status)->toBe(AttendanceStatus::Late);

    $hr = userWithAttendancePermission(PermissionName::AttendanceCorrect);

    $response = $this->actingAs($hr)->patchJson("/api/v1/attendance/{$record->id}/adjust", [
        'check_in' => '2026-08-24T09:05:00+00:00',
        'reason' => 'Badge log showed earlier entry',
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.status', 'PRESENT');
    $response->assertJsonPath('data.is_manual_adjustment', true);
});

test('adjust requires a reason', function () {
    $employee = Employee::factory()->create();
    $record = AttendanceRecord::factory()->create(['employee_id' => $employee->id]);
    $hr = userWithAttendancePermission(PermissionName::AttendanceCorrect);

    $response = $this->actingAs($hr)->patchJson("/api/v1/attendance/{$record->id}/adjust", [
        'check_in' => '2026-08-24T09:05:00+00:00',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['reason']);
});
