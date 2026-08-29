<?php

namespace App\Services;

use App\Enums\AttendanceEventType;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Enums\HalfDayPeriod;
use App\Enums\LeaveStatus;
use App\Enums\MissingCheckoutPolicy;
use App\Exceptions\AttendanceConflictException;
use App\Exceptions\AttendanceWindowException;
use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OrganizationSettings;
use App\Models\User;
use App\Support\AttendanceCloseSummary;
use App\Support\AttendanceToday;
use App\Support\ShiftResolution;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Check-in/check-out and the manual correction workflow. Grace/lateness
 * arithmetic here is deliberately small and duplicated nowhere else — see
 * classify() — because §132 makes it the one rule the whole product hinges
 * on: `check_in <= shift_start + grace` is NOT LATE, full stop.
 */
class AttendanceService
{
    public function __construct(private readonly ShiftService $shifts) {}

    /**
     * Backs `GET /api/v1/attendance/today`. `hasApprovedLeave` is a Phase 5
     * seam — always false until leave requests exist — mirroring how
     * ScopeResolver stubbed HR_SCOPE before Department/Team existed.
     */
    public function today(Employee $employee, ?Carbon $now = null): AttendanceToday
    {
        $settings = OrganizationSettings::current();
        $workDate = $this->orgToday($now ?? Carbon::now(), $settings);
        $resolution = $this->applyHalfDayAdjustment($employee, $workDate, $this->shifts->resolveForDate($employee, $workDate));

        $record = $this->findRecord($employee, $workDate);
        $hasApprovedLeave = $this->hasApprovedFullDayLeave($employee, $workDate);

        // §137 "check-in prompt suppression": never prompt on a weekend,
        // holiday, or full-day-approved-leave day, or once a check-in
        // already exists. A half-day leave (§138) does NOT suppress the
        // prompt — the employee is still expected for the other half.
        $shouldPrompt = $record === null
            && $resolution->isWorkDay
            && ! $hasApprovedLeave;

        return new AttendanceToday($workDate, $resolution, $hasApprovedLeave, $shouldPrompt, $record);
    }

    /**
     * §136 — a punch belongs to whichever of today's or yesterday's shift
     * window contains it (today takes priority when both would match), so
     * an overnight shift's late check-in never silently creates a second
     * day's record. §139.5 — at most one open check-in per (employee,
     * work_date); a second attempt is a 409 with the existing record.
     */
    public function checkIn(
        Employee $employee,
        User $actor,
        AttendanceSource $source,
        ?Carbon $at = null,
    ): AttendanceRecord {
        $now = ($at ?? Carbon::now())->clone();
        $settings = OrganizationSettings::current();
        $windowMinutes = $settings->attendance_checkin_window_minutes;

        $today = $this->orgToday($now, $settings);
        $yesterday = $today->copy()->subDay();

        $todayResolution = $this->shifts->resolveForDate($employee, $today);
        $yesterdayResolution = $this->shifts->resolveForDate($employee, $yesterday);

        $match = $this->matchWorkDate($now, $today, $todayResolution, $yesterday, $yesterdayResolution, $windowMinutes);

        if ($match === null) {
            throw new AttendanceWindowException(
                "Check-in at {$now->toIso8601String()} falls outside every shift's check-in window.",
            );
        }

        [$workDate, $resolution] = $match;
        $resolution = $this->applyHalfDayAdjustment($employee, $workDate, $resolution);

        $record = $this->findOrInitializeRecord($employee, $workDate, $resolution);

        if ($record->check_in !== null) {
            throw new AttendanceConflictException(
                'ALREADY_CHECKED_IN',
                $record,
                'This employee already checked in for this work date.',
            );
        }

        $classification = $this->classify($now, $resolution->shiftStart, $resolution->graceMinutes);

        $record->fill([
            'shift_id' => $resolution->shift?->id,
            'shift_start_used' => $resolution->shiftStart,
            'shift_end_used' => $resolution->shiftEnd,
            'grace_minutes_used' => $resolution->graceMinutes,
            'check_in' => $now,
            'late_minutes' => $classification['late_minutes'],
            'status' => $classification['status'],
        ])->save();

        AttendanceEvent::query()->create([
            'employee_id' => $employee->id,
            'event_type' => AttendanceEventType::CheckIn,
            'event_time' => $now,
            'source' => $source,
            'created_by' => $actor->id,
        ]);

        return $record->fresh();
    }

    /**
     * Checks out of the most recent open check-in, not necessarily
     * "today's" record — an overnight shift's checkout lands on the
     * following calendar day (§136).
     */
    public function checkOut(
        Employee $employee,
        User $actor,
        AttendanceSource $source,
        ?Carbon $at = null,
    ): AttendanceRecord {
        $now = ($at ?? Carbon::now())->clone();

        $record = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereNotNull('check_in')
            ->whereNull('check_out')
            ->orderByDesc('check_in')
            ->first();

        if ($record === null) {
            // Distinguish "already checked out" from "never checked in" by
            // looking at the most recent record regardless of check_out —
            // a nicer error for the common double-tap than one generic code.
            $mostRecent = AttendanceRecord::query()
                ->where('employee_id', $employee->id)
                ->whereNotNull('check_in')
                ->orderByDesc('check_in')
                ->first();

            if ($mostRecent !== null && $mostRecent->check_out !== null) {
                throw new AttendanceConflictException(
                    'ALREADY_CHECKED_OUT',
                    $mostRecent,
                    'This employee already checked out.',
                );
            }

            throw new AttendanceConflictException(
                'NOT_CHECKED_IN',
                null,
                'No open check-in was found to check out from.',
            );
        }

        $record->fill([
            'check_out' => $now,
            'worked_minutes' => (int) $record->check_in->diffInMinutes($now),
        ])->save();

        AttendanceEvent::query()->create([
            'employee_id' => $employee->id,
            'event_type' => AttendanceEventType::CheckOut,
            'event_time' => $now,
            'source' => $source,
            'created_by' => $actor->id,
        ]);

        return $record->fresh();
    }

    /**
     * §32/§97 manual correction. Recalculates late_minutes/status from a
     * changed check_in against the record's OWN already-snapshotted
     * shift_start_used/grace_minutes_used — never against today's live
     * settings (§95, §22) — unless the caller is overriding status
     * directly, in which case that override wins outright.
     *
     * @param  array{check_in?: ?string, check_out?: ?string, status?: string}  $changes
     */
    public function adjust(AttendanceRecord $record, array $changes, string $reason, User $changedBy): AttendanceRecord
    {
        foreach (['check_in', 'check_out', 'status'] as $field) {
            if (! array_key_exists($field, $changes)) {
                continue;
            }

            $oldValue = $this->adjustmentValue($record->{$field});
            $newRaw = $changes[$field];

            if ($field === 'status') {
                $record->status = AttendanceStatus::from($newRaw);
            } else {
                $record->{$field} = $newRaw !== null ? Carbon::parse($newRaw) : null;
            }

            $record->adjustments()->create([
                'field' => $field,
                'old_value' => $oldValue,
                'new_value' => $newRaw,
                'reason' => $reason,
                'changed_by' => $changedBy->id,
            ]);
        }

        if (array_key_exists('check_in', $changes) && ! array_key_exists('status', $changes)) {
            $classification = $this->classify($record->check_in, $record->shift_start_used, $record->grace_minutes_used);
            $record->late_minutes = $classification['late_minutes'];
            $record->status = $classification['status'];
        }

        if ($record->check_in !== null && $record->check_out !== null) {
            $record->worked_minutes = (int) $record->check_in->diffInMinutes($record->check_out);
        }

        $record->is_manual_adjustment = true;
        $record->save();

        return $record->fresh();
    }

    /**
     * The nightly close job (§137) — the only thing that ever produces
     * ABSENT/MISSING_CHECKOUT/HALF_DAY/WEEKEND/HOLIDAY records, since a
     * check-in-born record only ever starts as PRESENT/LATE. Idempotent:
     * re-running for the same date reaches the same conclusion, and a
     * record already flagged is_manual_adjustment is never touched.
     *
     * "Active" here means ACTIVE/PROBATION/NOTICE_PERIOD — employees
     * expected to attend. SUSPENDED is deliberately excluded: suspension
     * isn't an absence to deduct, it's a separate status HR already chose.
     */
    public function closeWorkDate(Carbon $workDate): AttendanceCloseSummary
    {
        $settings = OrganizationSettings::current();

        $employees = Employee::query()
            ->whereIn('status', [EmployeeStatus::Active, EmployeeStatus::Probation, EmployeeStatus::NoticePeriod])
            ->get();

        $absent = 0;
        $missingCheckout = 0;
        $halfDay = 0;
        $weekend = 0;
        $holiday = 0;
        $onLeave = 0;
        $unchanged = 0;
        $skipped = 0;

        foreach ($employees as $employee) {
            $record = $this->findRecord($employee, $workDate);

            if ($record?->is_manual_adjustment) {
                $skipped++;

                continue;
            }

            $resolution = $this->applyHalfDayAdjustment($employee, $workDate, $this->shifts->resolveForDate($employee, $workDate));
            $halfDayLeave = $this->approvedHalfDayLeave($employee, $workDate);

            $attributes = [
                'shift_id' => $resolution->shift?->id,
                'shift_start_used' => $resolution->shiftStart,
                'shift_end_used' => $resolution->shiftEnd,
                'grace_minutes_used' => $resolution->graceMinutes,
            ];

            if ($resolution->isHoliday) {
                $attributes['status'] = AttendanceStatus::Holiday;
                $holiday++;
            } elseif ($resolution->isWeekend) {
                $attributes['status'] = AttendanceStatus::Weekend;
                $weekend++;
            } elseif ($this->hasApprovedFullDayLeave($employee, $workDate)) {
                $attributes['status'] = AttendanceStatus::OnLeave;
                $onLeave++;
            } elseif ($halfDayLeave !== null) {
                // §138 — half-day leave + a valid half-day of attendance is
                // HALF_DAY (fully paid); below the threshold the worked
                // half doesn't count and the day falls to ABSENT.
                $workedMinutes = $record?->check_in !== null
                    ? ($record->worked_minutes ?? (
                        $record->check_out !== null
                            ? (int) $record->check_in->diffInMinutes($record->check_out)
                            : 0
                    ))
                    : 0;
                $halfDayThreshold = $settings->attendance_min_minutes_half_day ?? 0;

                if ($record?->check_in !== null && $workedMinutes >= $halfDayThreshold) {
                    $attributes['status'] = AttendanceStatus::HalfDay;
                    $halfDay++;
                } else {
                    $attributes['status'] = AttendanceStatus::Absent;
                    $absent++;
                }
            } elseif ($record === null || $record->check_in === null) {
                $attributes['status'] = AttendanceStatus::Absent;
                $absent++;
            } elseif ($record->check_out === null) {
                if ($settings->missing_checkout_policy === MissingCheckoutPolicy::AutoCloseAtShiftEnd
                    && $resolution->shiftEnd !== null) {
                    $attributes['check_out'] = $resolution->shiftEnd;
                    $attributes['worked_minutes'] = (int) $record->check_in->diffInMinutes($resolution->shiftEnd);
                }
                $attributes['status'] = AttendanceStatus::MissingCheckout;
                $missingCheckout++;
            } else {
                $workedMinutes = $record->worked_minutes ?? (int) $record->check_in->diffInMinutes($record->check_out);
                $halfDayThreshold = $settings->attendance_min_minutes_half_day;

                if ($halfDayThreshold !== null && $workedMinutes < $halfDayThreshold) {
                    $attributes['status'] = AttendanceStatus::HalfDay;
                    $halfDay++;
                } else {
                    $attributes['status'] = $record->status;
                    $unchanged++;
                }
            }

            if ($record === null) {
                AttendanceRecord::query()->create([
                    'employee_id' => $employee->id,
                    'work_date' => $workDate->toDateString(),
                    ...$attributes,
                ]);
            } else {
                $record->fill($attributes)->save();
            }
        }

        return new AttendanceCloseSummary(
            workDate: $workDate,
            absent: $absent,
            missingCheckout: $missingCheckout,
            halfDay: $halfDay,
            weekend: $weekend,
            holiday: $holiday,
            onLeave: $onLeave,
            unchanged: $unchanged,
            skippedManualAdjustment: $skipped,
        );
    }

    /**
     * @return array{late_minutes: int|null, status: AttendanceStatus}
     */
    private function classify(?CarbonInterface $checkIn, ?CarbonInterface $shiftStart, ?int $graceMinutes): array
    {
        if ($checkIn === null || $shiftStart === null) {
            return ['late_minutes' => null, 'status' => AttendanceStatus::Present];
        }

        // §136 early check-in: at or before shift start is always 0 late
        // minutes, never negative.
        $lateMinutes = $checkIn->lessThanOrEqualTo($shiftStart) ? 0 : (int) $shiftStart->diffInMinutes($checkIn);

        $graceEnd = $graceMinutes !== null ? $shiftStart->copy()->addMinutes($graceMinutes) : $shiftStart;

        // §132/§96: exactly at grace end is still on time.
        $isLate = $checkIn->gt($graceEnd);

        return [
            'late_minutes' => $lateMinutes,
            'status' => $isLate ? AttendanceStatus::Late : AttendanceStatus::Present,
        ];
    }

    /**
     * @return array{0: Carbon, 1: ShiftResolution}|null
     */
    private function matchWorkDate(
        Carbon $now,
        Carbon $today,
        ShiftResolution $todayResolution,
        Carbon $yesterday,
        ShiftResolution $yesterdayResolution,
        int $windowMinutes,
    ): ?array {
        if ($this->withinWindow($now, $todayResolution, $windowMinutes)) {
            return [$today, $todayResolution];
        }

        if ($this->withinWindow($now, $yesterdayResolution, $windowMinutes)) {
            return [$yesterday, $yesterdayResolution];
        }

        // Nothing to bound against when there's no shift on either
        // candidate date — accept unconditionally onto today.
        if ($todayResolution->shift === null && $yesterdayResolution->shift === null) {
            return [$today, $todayResolution];
        }

        return null;
    }

    private function withinWindow(Carbon $now, ShiftResolution $resolution, int $windowMinutes): bool
    {
        if ($resolution->shiftStart === null || $resolution->shiftEnd === null) {
            return false;
        }

        $windowStart = $resolution->shiftStart->copy()->subMinutes($windowMinutes);
        $windowEnd = $resolution->shiftEnd->copy()->addMinutes($windowMinutes);

        return $now->betweenIncluded($windowStart, $windowEnd);
    }

    private function findRecord(Employee $employee, Carbon $workDate): ?AttendanceRecord
    {
        return AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->where('work_date', $workDate->toDateString())
            ->first();
    }

    private function findOrInitializeRecord(Employee $employee, Carbon $workDate, ShiftResolution $resolution): AttendanceRecord
    {
        return $this->findRecord($employee, $workDate) ?? new AttendanceRecord([
            'employee_id' => $employee->id,
            'work_date' => $workDate->toDateString(),
            'shift_id' => $resolution->shift?->id,
            'status' => AttendanceStatus::Present,
        ]);
    }

    /**
     * §137/§138 — a FULL-day approved leave suppresses the check-in prompt
     * and, once nightly close runs, produces ON_LEAVE outright. A half-day
     * leave does neither — see approvedHalfDayLeave().
     */
    private function hasApprovedFullDayLeave(Employee $employee, Carbon $workDate): bool
    {
        return LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveStatus::HrApproved)
            ->where('is_half_day', false)
            ->where('start_date', '<=', $workDate->toDateString())
            ->where('end_date', '>=', $workDate->toDateString())
            ->exists();
    }

    /**
     * §138 — a half-day request only ever spans a single work day (§37's
     * LeaveService::submit() enforces start_date === end_date for one), so
     * this can never match more than one row.
     */
    private function approvedHalfDayLeave(Employee $employee, Carbon $workDate): ?LeaveRequest
    {
        return LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveStatus::HrApproved)
            ->where('is_half_day', true)
            ->where('start_date', $workDate->toDateString())
            ->first();
    }

    /**
     * §138 — a FIRST_HALF leave means the employee is only expected from
     * the shift midpoint onward, so grace (§16) applies from that adjusted
     * start instead of the nominal one. A SECOND_HALF leave doesn't move
     * the start (they still arrive on time); closeWorkDate() judges that
     * case purely on worked-minutes against the half-day threshold.
     */
    private function applyHalfDayAdjustment(Employee $employee, Carbon $workDate, ShiftResolution $resolution): ShiftResolution
    {
        $leave = $this->approvedHalfDayLeave($employee, $workDate);

        if ($leave === null
            || $leave->half_day_period !== HalfDayPeriod::FirstHalf
            || $resolution->shiftStart === null
            || $resolution->shiftEnd === null) {
            return $resolution;
        }

        $midpoint = $resolution->shiftStart->copy()->addMinutes(
            (int) ($resolution->shiftStart->diffInMinutes($resolution->shiftEnd) / 2),
        );

        return new ShiftResolution(
            workDate: $resolution->workDate,
            isWorkDay: $resolution->isWorkDay,
            isHoliday: $resolution->isHoliday,
            isWeekend: $resolution->isWeekend,
            shift: $resolution->shift,
            shiftStart: $midpoint,
            shiftEnd: $resolution->shiftEnd,
            graceMinutes: $resolution->graceMinutes,
            graceEnd: $resolution->graceMinutes !== null ? $midpoint->copy()->addMinutes($resolution->graceMinutes) : null,
        );
    }

    /**
     * The org-timezone calendar date `$instant` falls on — the anchor for
     * every "today"/"yesterday" candidate lookup (§142 — organization
     * timezone is authoritative, never the caller's).
     */
    private function orgToday(Carbon $instant, OrganizationSettings $settings): Carbon
    {
        $orgInstant = $instant->copy()->setTimezone($settings->timezone);

        return Carbon::parse($orgInstant->toDateString(), $settings->timezone);
    }

    private function adjustmentValue(mixed $value): ?string
    {
        return match (true) {
            $value instanceof CarbonInterface => $value->toIso8601String(),
            $value instanceof AttendanceStatus => $value->value,
            default => $value,
        };
    }
}
