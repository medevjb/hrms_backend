<?php

use App\Enums\EmployeeStatus;
use App\Enums\OvertimeApprovalStage;
use App\Enums\OvertimeStatus;
use App\Enums\OvertimeType;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\OrganizationSettings;
use App\Models\OvertimeRecord;
use App\Services\OvertimeService;
use Illuminate\Support\Carbon;

/**
 * docs/PRD.md §44/§45/§46/§52/§107 — overtime detection off finalised
 * attendance. §137's nightly close runs first; this is what runs next.
 */
function detectOvertime(string $date): void
{
    app(OvertimeService::class)->detectForWorkDate(Carbon::parse($date));
}

function workedAttendance(Employee $employee, string $date, int $workedMinutes): AttendanceRecord
{
    $checkIn = Carbon::parse("{$date} 09:00:00");

    return AttendanceRecord::factory()->create([
        'employee_id' => $employee->id,
        'work_date' => $date,
        'check_in' => $checkIn,
        'check_out' => $checkIn->copy()->addMinutes($workedMinutes),
        'worked_minutes' => $workedMinutes,
    ]);
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

test('a full day worked on a weekend is detected and enters the team leader stage', function () {
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Active, 'overtime_eligible' => true]);
    $attendance = workedAttendance($employee, '2026-08-22', 510); // a Saturday

    detectOvertime('2026-08-22');

    $record = OvertimeRecord::query()->where('employee_id', $employee->id)->sole();
    expect($record->type)->toBe(OvertimeType::Weekend)
        ->and($record->status)->toBe(OvertimeStatus::PendingTeamLeader)
        ->and($record->current_stage)->toBe(OvertimeApprovalStage::TeamLeader)
        ->and((float) $record->overtime_days)->toBe(1.0)
        ->and($record->attendance_record_id)->toBe($attendance->id)
        ->and($record->full_day_minutes_used)->toBe(480);
});

test('a full day worked on an official holiday is detected as holiday overtime', function () {
    $employee = Employee::factory()->create(['overtime_eligible' => true]);
    Holiday::factory()->create(['date' => '2026-08-25', 'active' => true]); // a Tuesday
    workedAttendance($employee, '2026-08-25', 490);

    detectOvertime('2026-08-25');

    expect(OvertimeRecord::query()->where('employee_id', $employee->id)->sole()->type)
        ->toBe(OvertimeType::Holiday);
});

test('a weekend day below the minimum duration is recorded but auto-rejected', function () {
    $employee = Employee::factory()->create(['overtime_eligible' => true]);
    workedAttendance($employee, '2026-08-22', 200);

    detectOvertime('2026-08-22');

    $record = OvertimeRecord::query()->where('employee_id', $employee->id)->sole();
    expect($record->status)->toBe(OvertimeStatus::Rejected)
        ->and($record->current_stage)->toBeNull()
        ->and((float) $record->overtime_days)->toBe(0.0)
        ->and($record->rejection_reason)->toContain('Insufficient working duration');
});

test('an employee who is not overtime eligible is skipped', function () {
    $employee = Employee::factory()->create(['overtime_eligible' => false]);
    workedAttendance($employee, '2026-08-22', 510);

    detectOvertime('2026-08-22');

    expect(OvertimeRecord::query()->where('employee_id', $employee->id)->exists())->toBeFalse();
});

test('weekend overtime disabled means a weekend day is not detected', function () {
    OrganizationSettings::current()->update(['weekend_overtime_enabled' => false]);
    $employee = Employee::factory()->create(['overtime_eligible' => true]);
    workedAttendance($employee, '2026-08-22', 510);

    detectOvertime('2026-08-22');

    expect(OvertimeRecord::query()->where('employee_id', $employee->id)->exists())->toBeFalse();
});

test('overtime disabled globally makes detection a no-op', function () {
    OrganizationSettings::current()->update(['overtime_enabled' => false]);
    $employee = Employee::factory()->create(['overtime_eligible' => true]);
    workedAttendance($employee, '2026-08-22', 510);

    detectOvertime('2026-08-22');

    expect(OvertimeRecord::query()->count())->toBe(0);
});

test('a weekend day with a check-in but no check-out is skipped', function () {
    $employee = Employee::factory()->create(['overtime_eligible' => true]);
    AttendanceRecord::factory()->create([
        'employee_id' => $employee->id,
        'work_date' => '2026-08-22',
        'check_in' => Carbon::parse('2026-08-22 09:00:00'),
        'check_out' => null,
    ]);

    detectOvertime('2026-08-22');

    expect(OvertimeRecord::query()->where('employee_id', $employee->id)->exists())->toBeFalse();
});

test('a non-active employee is skipped', function () {
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Suspended, 'overtime_eligible' => true]);
    workedAttendance($employee, '2026-08-22', 510);

    detectOvertime('2026-08-22');

    expect(OvertimeRecord::query()->where('employee_id', $employee->id)->exists())->toBeFalse();
});

test('detection is idempotent — a second run neither duplicates nor mutates an in-flight record', function () {
    $employee = Employee::factory()->create(['overtime_eligible' => true]);
    workedAttendance($employee, '2026-08-22', 510);

    detectOvertime('2026-08-22');
    $record = OvertimeRecord::query()->where('employee_id', $employee->id)->sole();
    $record->update(['status' => OvertimeStatus::PendingHr, 'current_stage' => OvertimeApprovalStage::Hr]);

    detectOvertime('2026-08-22');

    expect(OvertimeRecord::query()->where('employee_id', $employee->id)->count())->toBe(1)
        ->and($record->fresh()->status)->toBe(OvertimeStatus::PendingHr);
});

test('a weekday with no holiday earns no overtime', function () {
    $employee = Employee::factory()->create(['overtime_eligible' => true]);
    workedAttendance($employee, '2026-08-24', 600); // a Monday

    detectOvertime('2026-08-24');

    expect(OvertimeRecord::query()->where('employee_id', $employee->id)->exists())->toBeFalse();
});
