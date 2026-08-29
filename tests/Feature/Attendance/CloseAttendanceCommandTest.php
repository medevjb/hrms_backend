<?php

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Enums\PermissionName;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\Holiday;
use App\Models\OrganizationSettings;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use App\Models\UserRole;
use App\Notifications\AttendanceCloseSummary;
use App\Services\AttendanceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

function closeAttendanceFor(string $date)
{
    return app(AttendanceService::class)->closeWorkDate(Carbon::parse($date));
}

beforeEach(function () {
    OrganizationSettings::current()->update(['timezone' => 'UTC', 'weekend_days' => ['saturday', 'sunday']]);
});

test('an employee with no check-in on a work day is marked absent', function () {
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Active]);
    $shift = Shift::factory()->create(['start_time' => '09:00:00']);
    EmployeeShift::factory()->create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'started_at' => '2026-01-01']);

    closeAttendanceFor('2026-08-24'); // a Monday

    $record = AttendanceRecord::query()->where('employee_id', $employee->id)->where('work_date', '2026-08-24')->first();
    expect($record->status)->toBe(AttendanceStatus::Absent);
});

test('a weekend produces WEEKEND with no deduction, even with a shift assigned', function () {
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Active]);
    $shift = Shift::factory()->create(['start_time' => '09:00:00']);
    EmployeeShift::factory()->create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'started_at' => '2026-01-01']);

    closeAttendanceFor('2026-08-22'); // a Saturday

    $record = AttendanceRecord::query()->where('employee_id', $employee->id)->first();
    expect($record->status)->toBe(AttendanceStatus::Weekend);
});

test('a holiday produces HOLIDAY, taking priority over weekend/absence', function () {
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Active]);
    Holiday::factory()->create(['date' => '2026-08-24', 'active' => true]);

    closeAttendanceFor('2026-08-24');

    $record = AttendanceRecord::query()->where('employee_id', $employee->id)->first();
    expect($record->status)->toBe(AttendanceStatus::Holiday);
});

test('checked in with no checkout becomes MISSING_CHECKOUT under the default LEAVE_OPEN policy', function () {
    OrganizationSettings::current()->update(['missing_checkout_policy' => 'LEAVE_OPEN']);
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Active]);
    $shift = Shift::factory()->create(['start_time' => '09:00:00', 'end_time' => '18:00:00']);
    EmployeeShift::factory()->create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'started_at' => '2026-01-01']);
    app(AttendanceService::class)->checkIn($employee, User::factory()->create(), AttendanceSource::Web, Carbon::parse('2026-08-24 09:00:00'));

    closeAttendanceFor('2026-08-24');

    $record = AttendanceRecord::query()->where('employee_id', $employee->id)->first();
    expect($record->status)->toBe(AttendanceStatus::MissingCheckout);
    expect($record->check_out)->toBeNull();
    expect($record->worked_minutes)->toBeNull();
});

test('AUTO_CLOSE_AT_SHIFT_END credits worked time up to shift end but still flags MISSING_CHECKOUT', function () {
    OrganizationSettings::current()->update(['missing_checkout_policy' => 'AUTO_CLOSE_AT_SHIFT_END']);
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Active]);
    $shift = Shift::factory()->create(['start_time' => '09:00:00', 'end_time' => '18:00:00']);
    EmployeeShift::factory()->create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'started_at' => '2026-01-01']);
    app(AttendanceService::class)->checkIn($employee, User::factory()->create(), AttendanceSource::Web, Carbon::parse('2026-08-24 09:00:00'));

    closeAttendanceFor('2026-08-24');

    $record = AttendanceRecord::query()->where('employee_id', $employee->id)->first();
    expect($record->status)->toBe(AttendanceStatus::MissingCheckout);
    expect($record->check_out->toDateTimeString())->toBe('2026-08-24 18:00:00');
    expect($record->worked_minutes)->toBe(540);
});

test('a fully worked day below the half-day threshold is reclassified HALF_DAY', function () {
    OrganizationSettings::current()->update(['attendance_min_minutes_half_day' => 240]);
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Active]);
    $shift = Shift::factory()->create(['start_time' => '09:00:00', 'end_time' => '18:00:00']);
    EmployeeShift::factory()->create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'started_at' => '2026-01-01']);
    $service = app(AttendanceService::class);
    $actor = User::factory()->create();
    $service->checkIn($employee, $actor, AttendanceSource::Web, Carbon::parse('2026-08-24 09:00:00'));
    $service->checkOut($employee, $actor, AttendanceSource::Web, Carbon::parse('2026-08-24 11:00:00'));

    closeAttendanceFor('2026-08-24');

    $record = AttendanceRecord::query()->where('employee_id', $employee->id)->first();
    expect($record->status)->toBe(AttendanceStatus::HalfDay);
});

test('a normal present/late day is left unchanged by the close job', function () {
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Active]);
    $shift = Shift::factory()->create(['start_time' => '09:00:00', 'end_time' => '18:00:00']);
    EmployeeShift::factory()->create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'started_at' => '2026-01-01']);
    $service = app(AttendanceService::class);
    $actor = User::factory()->create();
    $service->checkIn($employee, $actor, AttendanceSource::Web, Carbon::parse('2026-08-24 09:00:00'));
    $service->checkOut($employee, $actor, AttendanceSource::Web, Carbon::parse('2026-08-24 18:00:00'));

    closeAttendanceFor('2026-08-24');

    $record = AttendanceRecord::query()->where('employee_id', $employee->id)->first();
    expect($record->status)->toBe(AttendanceStatus::Present);
});

test('a manually adjusted record is never overwritten by the close job', function () {
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Active]);
    AttendanceRecord::factory()->create([
        'employee_id' => $employee->id,
        'work_date' => '2026-08-24',
        'status' => AttendanceStatus::Present,
        'is_manual_adjustment' => true,
    ]);

    closeAttendanceFor('2026-08-24');

    $record = AttendanceRecord::query()->where('employee_id', $employee->id)->first();
    expect($record->status)->toBe(AttendanceStatus::Present);
});

test('re-running the close job for the same date is idempotent', function () {
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Active]);
    $shift = Shift::factory()->create(['start_time' => '09:00:00']);
    EmployeeShift::factory()->create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'started_at' => '2026-01-01']);

    closeAttendanceFor('2026-08-24');
    closeAttendanceFor('2026-08-24');

    expect(AttendanceRecord::query()->where('employee_id', $employee->id)->count())->toBe(1);
});

test('a suspended employee is not marked absent', function () {
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Suspended]);

    closeAttendanceFor('2026-08-24');

    expect(AttendanceRecord::query()->where('employee_id', $employee->id)->exists())->toBeFalse();
});

test('the close command sends exactly one summary notification, not one per employee', function () {
    Notification::fake();

    $hr = User::factory()->create();
    $role = Role::factory()->create();
    $permission = Permission::query()->firstOrCreate(['name' => PermissionName::AttendanceManage->value]);
    $role->permissions()->attach($permission);
    UserRole::factory()->create(['user_id' => $hr->id, 'role_id' => $role->id]);

    Employee::factory()->count(3)->create(['status' => EmployeeStatus::Active]);

    $this->artisan('attendance:close', ['date' => '2026-08-24'])->assertExitCode(0);

    Notification::assertSentTo($hr, AttendanceCloseSummary::class, function ($notification, $channels) {
        return true;
    });
    Notification::assertCount(1);
});
