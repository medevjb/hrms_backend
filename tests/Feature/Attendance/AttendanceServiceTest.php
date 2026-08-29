<?php

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Exceptions\AttendanceConflictException;
use App\Exceptions\AttendanceWindowException;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\OrganizationSettings;
use App\Models\Shift;
use App\Models\ShiftOverride;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Support\Carbon;

function assignShift(Employee $employee, Shift $shift, string $startedAt = '2026-01-01'): void
{
    EmployeeShift::factory()->create([
        'employee_id' => $employee->id,
        'shift_id' => $shift->id,
        'started_at' => $startedAt,
    ]);
}

function attendanceCheckIn(Employee $employee, string $at, ?User $actor = null)
{
    return app(AttendanceService::class)->checkIn(
        $employee,
        $actor ?? User::factory()->create(),
        AttendanceSource::Web,
        Carbon::parse($at),
    );
}

function attendanceCheckOut(Employee $employee, string $at, ?User $actor = null)
{
    return app(AttendanceService::class)->checkOut(
        $employee,
        $actor ?? User::factory()->create(),
        AttendanceSource::Web,
        Carbon::parse($at),
    );
}

beforeEach(function () {
    OrganizationSettings::current()->update(['timezone' => 'UTC', 'late_grace_minutes' => 10]);
});

// §115 mandatory cases: shift 09:00, grace 10.
test('the mandatory grace boundary cases', function (string $checkInTime, bool $expectedLate) {
    $employee = Employee::factory()->create();
    $shift = Shift::factory()->create(['start_time' => '09:00:00', 'end_time' => '18:00:00', 'late_grace_minutes' => null]);
    assignShift($employee, $shift);

    $record = attendanceCheckIn($employee, "2026-08-24 {$checkInTime}");

    expect($record->status)->toBe($expectedLate ? AttendanceStatus::Late : AttendanceStatus::Present);
})->with([
    ['08:59:00', false],
    ['09:00:00', false],
    ['09:05:00', false],
    ['09:09:00', false],
    ['09:10:00', false],
    ['09:11:00', true],
    ['09:30:00', true],
]);

test('late minutes record the raw difference from shift start, independent of grace', function () {
    $employee = Employee::factory()->create();
    $shift = Shift::factory()->create(['start_time' => '09:00:00', 'late_grace_minutes' => null]);
    assignShift($employee, $shift);

    $record = attendanceCheckIn($employee, '2026-08-24 09:18:00');

    expect($record->late_minutes)->toBe(18);
    expect($record->status)->toBe(AttendanceStatus::Late);
});

test('an early check-in is present with zero late minutes, never negative', function () {
    $employee = Employee::factory()->create();
    $shift = Shift::factory()->create(['start_time' => '09:00:00', 'late_grace_minutes' => null]);
    assignShift($employee, $shift);

    $record = attendanceCheckIn($employee, '2026-08-24 08:40:00');

    expect($record->late_minutes)->toBe(0);
    expect($record->status)->toBe(AttendanceStatus::Present);
});

test('grace set to 0: exactly on time is present, one minute after is late', function () {
    $employee = Employee::factory()->create();
    $shift = Shift::factory()->create(['start_time' => '09:00:00', 'late_grace_minutes' => null]);
    assignShift($employee, $shift);
    OrganizationSettings::current()->update(['late_grace_minutes' => 0]);

    expect(attendanceCheckIn($employee, '2026-08-24 09:00:00')->status)->toBe(AttendanceStatus::Present);

    $employee2 = Employee::factory()->create();
    assignShift($employee2, $shift);
    expect(attendanceCheckIn($employee2, '2026-08-24 09:01:00')->status)->toBe(AttendanceStatus::Late);
});

test('a shift-level grace override wins over the organization default', function () {
    OrganizationSettings::current()->update(['late_grace_minutes' => 5]);
    $employee = Employee::factory()->create();
    $shift = Shift::factory()->create(['start_time' => '09:00:00', 'late_grace_minutes' => 20]);
    assignShift($employee, $shift);

    // 09:18 is later than the org's 5-minute grace would allow, but inside
    // the shift's own 20-minute override.
    $record = attendanceCheckIn($employee, '2026-08-24 09:18:00');

    expect($record->status)->toBe(AttendanceStatus::Present);
    expect($record->grace_minutes_used)->toBe(20);
});

test('a temporary shift override changes what counts as on time for that day (§98)', function () {
    $employee = Employee::factory()->create();
    $regular = Shift::factory()->create(['name' => 'Regular', 'start_time' => '09:00:00', 'late_grace_minutes' => null]);
    $temporary = Shift::factory()->create(['name' => 'Temporary', 'start_time' => '12:00:00', 'late_grace_minutes' => null]);
    assignShift($employee, $regular);
    ShiftOverride::factory()->create([
        'employee_id' => $employee->id,
        'shift_id' => $temporary->id,
        'work_date' => '2026-08-24',
    ]);
    OrganizationSettings::current()->update(['late_grace_minutes' => 10]);

    // 12:09 would be very late against the regular 09:00 shift, but on
    // time against the day's actual (temporary) 12:00 shift.
    $record = attendanceCheckIn($employee, '2026-08-24 12:09:00');

    expect($record->status)->toBe(AttendanceStatus::Present);
    expect($record->shift_id)->toBe($temporary->id);
});

test('a duplicate check-in is rejected with the existing record attached', function () {
    $employee = Employee::factory()->create();
    $shift = Shift::factory()->create(['start_time' => '09:00:00']);
    assignShift($employee, $shift);
    $first = attendanceCheckIn($employee, '2026-08-24 09:05:00');

    try {
        attendanceCheckIn($employee, '2026-08-24 09:20:00');
        expect(false)->toBeTrue('Expected AttendanceConflictException');
    } catch (AttendanceConflictException $exception) {
        expect($exception->errorCode)->toBe('ALREADY_CHECKED_IN');
        expect($exception->record->id)->toBe($first->id);
    }
});

test('checking out without an open check-in is rejected', function () {
    $employee = Employee::factory()->create();

    try {
        attendanceCheckOut($employee, '2026-08-24 18:00:00');
        expect(false)->toBeTrue('Expected AttendanceConflictException');
    } catch (AttendanceConflictException $exception) {
        expect($exception->errorCode)->toBe('NOT_CHECKED_IN');
    }
});

test('checking out twice is rejected the second time', function () {
    $employee = Employee::factory()->create();
    $shift = Shift::factory()->create(['start_time' => '09:00:00']);
    assignShift($employee, $shift);
    attendanceCheckIn($employee, '2026-08-24 09:00:00');
    attendanceCheckOut($employee, '2026-08-24 18:00:00');

    try {
        attendanceCheckOut($employee, '2026-08-24 18:05:00');
        expect(false)->toBeTrue('Expected AttendanceConflictException');
    } catch (AttendanceConflictException $exception) {
        expect($exception->errorCode)->toBe('ALREADY_CHECKED_OUT');
    }
});

test('checkout computes worked minutes from check-in to check-out', function () {
    $employee = Employee::factory()->create();
    $shift = Shift::factory()->create(['start_time' => '09:00:00']);
    assignShift($employee, $shift);
    attendanceCheckIn($employee, '2026-08-24 09:07:00');

    $record = attendanceCheckOut($employee, '2026-08-24 17:07:00');

    expect($record->worked_minutes)->toBe(480);
});

test('an overnight shift check-in and check-out attach to the shift-start date (§136)', function () {
    $employee = Employee::factory()->create();
    $shift = Shift::factory()->overnight()->create(['start_time' => '20:00:00', 'end_time' => '05:00:00', 'late_grace_minutes' => null]);
    assignShift($employee, $shift);

    $checkIn = attendanceCheckIn($employee, '2026-08-24 19:58:00');
    expect($checkIn->work_date->toDateString())->toBe('2026-08-24');
    expect($checkIn->status)->toBe(AttendanceStatus::Present);

    $checkOut = attendanceCheckOut($employee, '2026-08-25 05:04:00');
    expect($checkOut->work_date->toDateString())->toBe('2026-08-24');
    // 2026-08-24 19:58 → 2026-08-25 05:04 is 9h06m = 546 minutes.
    expect($checkOut->worked_minutes)->toBe(546);
});

test('a punch outside every shift window is rejected, not silently attached to a day', function () {
    $employee = Employee::factory()->create();
    $shift = Shift::factory()->create(['start_time' => '09:00:00', 'end_time' => '18:00:00']);
    assignShift($employee, $shift);
    OrganizationSettings::current()->update(['attendance_checkin_window_minutes' => 60]);

    expect(fn () => attendanceCheckIn($employee, '2026-08-24 02:00:00'))
        ->toThrow(AttendanceWindowException::class);
});

test('an employee with no shift at all can still check in, unconditionally onto today', function () {
    $employee = Employee::factory()->create();

    $record = attendanceCheckIn($employee, '2026-08-24 03:00:00');

    expect($record->work_date->toDateString())->toBe('2026-08-24');
    expect($record->status)->toBe(AttendanceStatus::Present);
    expect($record->late_minutes)->toBeNull();
});

test('a manual correction to check-in recalculates status against the snapshotted grace (§97)', function () {
    $employee = Employee::factory()->create();
    $shift = Shift::factory()->create(['start_time' => '09:00:00', 'late_grace_minutes' => null]);
    assignShift($employee, $shift);
    OrganizationSettings::current()->update(['late_grace_minutes' => 10]);

    $record = attendanceCheckIn($employee, '2026-08-24 09:15:00');
    expect($record->status)->toBe(AttendanceStatus::Late);

    // Settings change after the fact — must not affect the correction below.
    OrganizationSettings::current()->update(['late_grace_minutes' => 20]);

    $hr = User::factory()->create();
    $corrected = app(AttendanceService::class)->adjust(
        $record,
        ['check_in' => '2026-08-24T09:08:00+00:00'],
        'Employee showed a valid badge log',
        $hr,
    );

    expect($corrected->status)->toBe(AttendanceStatus::Present);
    expect($corrected->grace_minutes_used)->toBe(10); // still the original snapshot
    expect($corrected->is_manual_adjustment)->toBeTrue();
    expect($corrected->adjustments()->count())->toBe(1);
    expect($corrected->adjustments()->first()->field)->toBe('check_in');
});

test('changing the organization grace setting does not alter a past record (§22, §95)', function () {
    $employee = Employee::factory()->create();
    $shift = Shift::factory()->create(['start_time' => '09:00:00', 'late_grace_minutes' => null]);
    assignShift($employee, $shift);
    OrganizationSettings::current()->update(['late_grace_minutes' => 10]);

    $record = attendanceCheckIn($employee, '2026-08-24 09:09:00');
    expect($record->grace_minutes_used)->toBe(10);

    OrganizationSettings::current()->update(['late_grace_minutes' => 20]);

    expect($record->fresh()->grace_minutes_used)->toBe(10);
    expect($record->fresh()->status)->toBe(AttendanceStatus::Present);
});
