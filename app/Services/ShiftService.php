<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\OrganizationSettings;
use App\Models\Shift;
use App\Models\ShiftOverride;
use App\Support\ShiftResolution;
use Illuminate\Support\Carbon;

/**
 * The service docs/PRD.md §104's completion condition is actually about:
 * given an employee and a date, can the backend say what shift applies,
 * when it starts/ends, when the grace period ends, and whether the date
 * is a holiday/weekend/work day. §125 — never hard-code grace minutes;
 * everything here reads through OrganizationSettings/Shift.
 */
class ShiftService
{
    public function resolveForDate(Employee $employee, Carbon $date): ShiftResolution
    {
        $settings = OrganizationSettings::current();

        // Anchor the calendar date to the org timezone regardless of what
        // timezone the caller's Carbon instance happened to carry (§142 —
        // the organization timezone is authoritative for attendance).
        $workDate = Carbon::parse($date->toDateString(), $settings->timezone);

        $isWeekend = $settings->isWeekend($workDate);
        $isHoliday = Holiday::query()
            ->where('date', $workDate->toDateString())
            ->where('active', true)
            ->exists();

        $shift = $this->resolveShiftFor($employee, $workDate);

        if ($shift === null) {
            return new ShiftResolution(
                workDate: $workDate,
                isWorkDay: ! $isWeekend && ! $isHoliday,
                isHoliday: $isHoliday,
                isWeekend: $isWeekend,
                shift: null,
                shiftStart: null,
                shiftEnd: null,
                graceMinutes: null,
                graceEnd: null,
            );
        }

        $shiftStart = $workDate->copy()->setTimeFromTimeString($shift->start_time);

        // §136 — an overnight shift (end_time <= start_time) belongs to
        // its start date; the end time lands on the following calendar day.
        $shiftEnd = $shift->isOvernight()
            ? $workDate->copy()->addDay()->setTimeFromTimeString($shift->end_time)
            : $workDate->copy()->setTimeFromTimeString($shift->end_time);

        $graceMinutes = $shift->resolveGraceMinutes($settings);
        $graceEnd = $shiftStart->copy()->addMinutes($graceMinutes);

        return new ShiftResolution(
            workDate: $workDate,
            isWorkDay: ! $isWeekend && ! $isHoliday,
            isHoliday: $isHoliday,
            isWeekend: $isWeekend,
            shift: $shift,
            shiftStart: $shiftStart,
            shiftEnd: $shiftEnd,
            graceMinutes: $graceMinutes,
            graceEnd: $graceEnd,
        );
    }

    /**
     * A shift_override for this exact date always wins over the employee's
     * regular assignment (docs/PRD.md §23) — it does not change what
     * currentShift() reports for any other date.
     */
    private function resolveShiftFor(Employee $employee, Carbon $workDate): ?Shift
    {
        $override = ShiftOverride::query()
            ->where('employee_id', $employee->id)
            ->where('work_date', $workDate->toDateString())
            ->first();

        return $override !== null ? $override->shift : $employee->currentShift();
    }
}
