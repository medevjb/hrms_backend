<?php

use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\Holiday;
use App\Models\OrganizationSettings;
use App\Models\Shift;
use App\Models\ShiftOverride;
use App\Services\ShiftService;
use App\Support\ShiftResolution;
use Illuminate\Support\Carbon;

function resolveShiftForEmployee(Employee $employee, string $date): ShiftResolution
{
    return app(ShiftService::class)->resolveForDate($employee, Carbon::parse($date));
}

test('an employee with no shift assignment resolves with a null shift but a correct work-day fact', function () {
    $employee = Employee::factory()->create();

    $resolution = resolveShiftForEmployee($employee, '2026-08-25'); // a Tuesday

    expect($resolution->shift)->toBeNull();
    expect($resolution->shiftStart)->toBeNull();
    expect($resolution->shiftEnd)->toBeNull();
    expect($resolution->graceMinutes)->toBeNull();
    expect($resolution->graceEnd)->toBeNull();
    expect($resolution->isWorkDay)->toBeTrue();
    expect($resolution->isWeekend)->toBeFalse();
    expect($resolution->isHoliday)->toBeFalse();
});

test('a regular shift resolves shift start/end from start_time/end_time on the work date', function () {
    $employee = Employee::factory()->create();
    $shift = Shift::factory()->create(['start_time' => '09:00:00', 'end_time' => '18:00:00']);
    EmployeeShift::factory()->create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'started_at' => '2026-01-01']);

    $resolution = resolveShiftForEmployee($employee, '2026-08-25');

    expect($resolution->shift->id)->toBe($shift->id);
    expect($resolution->shiftStart->toDateTimeString())->toBe('2026-08-25 09:00:00');
    expect($resolution->shiftEnd->toDateTimeString())->toBe('2026-08-25 18:00:00');
});

test('an overnight shift attributes its end time to the following calendar day', function () {
    $employee = Employee::factory()->create();
    $shift = Shift::factory()->overnight()->create(['start_time' => '20:00:00', 'end_time' => '05:00:00']);
    EmployeeShift::factory()->create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'started_at' => '2026-01-01']);

    $resolution = resolveShiftForEmployee($employee, '2026-08-25');

    expect($resolution->shiftStart->toDateTimeString())->toBe('2026-08-25 20:00:00');
    expect($resolution->shiftEnd->toDateTimeString())->toBe('2026-08-26 05:00:00');
});

test('grace minutes fall back to the organization default when the shift has none set', function () {
    OrganizationSettings::current()->update(['late_grace_minutes' => 15]);
    $employee = Employee::factory()->create();
    $shift = Shift::factory()->create(['start_time' => '09:00:00', 'late_grace_minutes' => null]);
    EmployeeShift::factory()->create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'started_at' => '2026-01-01']);

    $resolution = resolveShiftForEmployee($employee, '2026-08-25');

    expect($resolution->graceMinutes)->toBe(15);
    expect($resolution->graceEnd->toDateTimeString())->toBe('2026-08-25 09:15:00');
});

test('a shift-specific grace override wins over the organization default', function () {
    OrganizationSettings::current()->update(['late_grace_minutes' => 15]);
    $employee = Employee::factory()->create();
    $shift = Shift::factory()->create(['start_time' => '09:00:00', 'late_grace_minutes' => 20]);
    EmployeeShift::factory()->create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'started_at' => '2026-01-01']);

    $resolution = resolveShiftForEmployee($employee, '2026-08-25');

    expect($resolution->graceMinutes)->toBe(20);
    expect($resolution->graceEnd->toDateTimeString())->toBe('2026-08-25 09:20:00');
});

test('a shift override for the exact date wins over the regular assignment, without changing it', function () {
    $employee = Employee::factory()->create();
    $regular = Shift::factory()->create(['name' => 'Regular', 'start_time' => '09:00:00', 'end_time' => '18:00:00']);
    $temporary = Shift::factory()->create(['name' => 'Temporary', 'start_time' => '12:00:00', 'end_time' => '21:00:00']);
    EmployeeShift::factory()->create(['employee_id' => $employee->id, 'shift_id' => $regular->id, 'started_at' => '2026-01-01']);
    ShiftOverride::factory()->create(['employee_id' => $employee->id, 'shift_id' => $temporary->id, 'work_date' => '2026-08-20']);

    $onOverrideDay = resolveShiftForEmployee($employee, '2026-08-20');
    $onAnyOtherDay = resolveShiftForEmployee($employee, '2026-08-21');

    expect($onOverrideDay->shift->id)->toBe($temporary->id);
    expect($onOverrideDay->shiftStart->toDateTimeString())->toBe('2026-08-20 12:00:00');
    expect($onAnyOtherDay->shift->id)->toBe($regular->id);
});

test('weekend is read from organization_settings.weekend_days, not hard-coded', function () {
    OrganizationSettings::current()->update(['weekend_days' => ['friday']]);
    $employee = Employee::factory()->create();

    expect(resolveShiftForEmployee($employee, '2026-08-28')->isWeekend)->toBeTrue(); // a Friday
    expect(resolveShiftForEmployee($employee, '2026-08-28')->isWorkDay)->toBeFalse();
    expect(resolveShiftForEmployee($employee, '2026-08-29')->isWeekend)->toBeFalse(); // a Saturday
});

test('an active holiday makes the date not a work day even on a weekday', function () {
    Holiday::factory()->create(['date' => '2026-09-03', 'active' => true]);
    $employee = Employee::factory()->create();

    $resolution = resolveShiftForEmployee($employee, '2026-09-03');

    expect($resolution->isHoliday)->toBeTrue();
    expect($resolution->isWorkDay)->toBeFalse();
});

test('an inactive holiday does not affect the work-day fact', function () {
    Holiday::factory()->create(['date' => '2026-09-03', 'active' => false]);
    $employee = Employee::factory()->create();

    $resolution = resolveShiftForEmployee($employee, '2026-09-03');

    expect($resolution->isHoliday)->toBeFalse();
    expect($resolution->isWorkDay)->toBeTrue();
});

test('a shift still resolves on a weekend or holiday, since weekend/holiday overtime needs to know it', function () {
    Holiday::factory()->create(['date' => '2026-09-03', 'active' => true]);
    $employee = Employee::factory()->create();
    $shift = Shift::factory()->create(['start_time' => '09:00:00', 'end_time' => '18:00:00']);
    EmployeeShift::factory()->create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'started_at' => '2026-01-01']);

    $resolution = resolveShiftForEmployee($employee, '2026-09-03');

    expect($resolution->isWorkDay)->toBeFalse();
    expect($resolution->shift->id)->toBe($shift->id);
    expect($resolution->shiftStart)->not->toBeNull();
});
