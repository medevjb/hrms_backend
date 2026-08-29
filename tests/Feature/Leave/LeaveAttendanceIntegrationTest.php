<?php

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Enums\HalfDayPeriod;
use App\Enums\LeaveStatus;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OrganizationSettings;
use App\Models\Shift;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Support\Carbon;

/**
 * docs/PRD.md §137/§138 — the seam AttendanceService::today()/closeWorkDate()
 * left for Phase 5 (hasApprovedLeave() was hard-coded false) now has real
 * leave requests to query. §138 additionally connects half-day leave to
 * attendance, which neither phase's own mandatory test list names alone.
 */
function approvedLeave(Employee $employee, string $date, bool $isHalfDay = false, ?HalfDayPeriod $period = null): LeaveRequest
{
    $leaveType = LeaveType::factory()->create(['annual_allocation_days' => 15]);

    return LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => $date,
        'end_date' => $date,
        'is_half_day' => $isHalfDay,
        'half_day_period' => $period,
        'days_requested' => $isHalfDay ? 0.5 : 1,
        'status' => LeaveStatus::HrApproved,
        'current_stage' => null,
        'decided_at' => Carbon::now(),
    ]);
}

beforeEach(function () {
    OrganizationSettings::current()->update(['timezone' => 'UTC', 'late_grace_minutes' => 10, 'attendance_min_minutes_half_day' => 180]);
});

test('a full-day approved leave suppresses the check-in prompt', function () {
    $employee = Employee::factory()->create();
    $shift = Shift::factory()->create(['start_time' => '09:00:00']);
    EmployeeShift::factory()->create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'started_at' => '2026-01-01']);
    approvedLeave($employee, '2026-08-24');

    $today = app(AttendanceService::class)->today($employee, Carbon::parse('2026-08-24 09:00:00'));

    expect($today->hasApprovedLeave)->toBeTrue();
    expect($today->shouldPromptCheckIn)->toBeFalse();
});

test('nightly close marks a full-day-approved-leave day ON_LEAVE', function () {
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Active]);
    $shift = Shift::factory()->create(['start_time' => '09:00:00']);
    EmployeeShift::factory()->create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'started_at' => '2026-01-01']);
    approvedLeave($employee, '2026-08-24');

    app(AttendanceService::class)->closeWorkDate(Carbon::parse('2026-08-24'));

    $record = AttendanceRecord::query()->where('employee_id', $employee->id)->where('work_date', '2026-08-24')->first();
    expect($record->status)->toBe(AttendanceStatus::OnLeave);
});

test('a half-day leave does NOT suppress the check-in prompt — the employee still owes the other half', function () {
    $employee = Employee::factory()->create();
    $shift = Shift::factory()->create(['start_time' => '09:00:00', 'end_time' => '17:00:00']);
    EmployeeShift::factory()->create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'started_at' => '2026-01-01']);
    approvedLeave($employee, '2026-08-24', true, HalfDayPeriod::FirstHalf);

    $today = app(AttendanceService::class)->today($employee, Carbon::parse('2026-08-24 09:00:00'));

    expect($today->hasApprovedLeave)->toBeFalse();
    expect($today->shouldPromptCheckIn)->toBeTrue();
});

test('§138 — checking in at the adjusted midpoint after a first-half leave is NOT late', function () {
    $employee = Employee::factory()->create();
    // 09:00-17:00 shift, midpoint 13:00; a FIRST_HALF leave moves the
    // effective start (and grace) to 13:00.
    $shift = Shift::factory()->create(['start_time' => '09:00:00', 'end_time' => '17:00:00', 'late_grace_minutes' => null]);
    EmployeeShift::factory()->create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'started_at' => '2026-01-01']);
    approvedLeave($employee, '2026-08-24', true, HalfDayPeriod::FirstHalf);

    $record = app(AttendanceService::class)->checkIn(
        $employee,
        User::factory()->create(),
        AttendanceSource::Web,
        Carbon::parse('2026-08-24 13:05:00'), // 5 minutes after the adjusted 13:00 start, within the 10-minute grace
    );

    expect($record->status)->toBe(AttendanceStatus::Present);
    expect($record->shift_start_used->format('H:i'))->toBe('13:00');
});

test('§138 — a first-half leave plus a valid half-day of attendance closes as fully-paid HALF_DAY', function () {
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Active]);
    $shift = Shift::factory()->create(['start_time' => '09:00:00', 'end_time' => '17:00:00', 'late_grace_minutes' => null]);
    EmployeeShift::factory()->create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'started_at' => '2026-01-01']);
    approvedLeave($employee, '2026-08-24', true, HalfDayPeriod::FirstHalf);

    $attendance = app(AttendanceService::class);
    $attendance->checkIn($employee, User::factory()->create(), AttendanceSource::Web, Carbon::parse('2026-08-24 13:00:00'));
    $attendance->checkOut($employee, User::factory()->create(), AttendanceSource::Web, Carbon::parse('2026-08-24 17:00:00'));

    $attendance->closeWorkDate(Carbon::parse('2026-08-24'));

    $record = AttendanceRecord::query()->where('employee_id', $employee->id)->where('work_date', '2026-08-24')->first();
    expect($record->status)->toBe(AttendanceStatus::HalfDay);
});

test('§138 — a first-half leave with no attendance for the other half closes ABSENT', function () {
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Active]);
    $shift = Shift::factory()->create(['start_time' => '09:00:00', 'end_time' => '17:00:00']);
    EmployeeShift::factory()->create(['employee_id' => $employee->id, 'shift_id' => $shift->id, 'started_at' => '2026-01-01']);
    approvedLeave($employee, '2026-08-24', true, HalfDayPeriod::FirstHalf);

    app(AttendanceService::class)->closeWorkDate(Carbon::parse('2026-08-24'));

    $record = AttendanceRecord::query()->where('employee_id', $employee->id)->where('work_date', '2026-08-24')->first();
    expect($record->status)->toBe(AttendanceStatus::Absent);
});
