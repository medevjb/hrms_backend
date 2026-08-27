<?php

namespace App\Support;

use App\Models\Shift;
use Illuminate\Support\Carbon;

/**
 * Everything docs/PRD.md §104's completion condition asks for, given an
 * employee and a date. `shift`/`shiftStart`/`shiftEnd`/`graceMinutes`/
 * `graceEnd` resolve whenever the employee has a shift for this date at
 * all — even on a weekend/holiday, since weekend/holiday overtime (§44,
 * §45) still needs to know what shift they'd have worked. `isWorkDay` is
 * the separate, calendar-only fact of whether attendance is expected.
 */
readonly class ShiftResolution
{
    public function __construct(
        public Carbon $workDate,
        public bool $isWorkDay,
        public bool $isHoliday,
        public bool $isWeekend,
        public ?Shift $shift,
        public ?Carbon $shiftStart,
        public ?Carbon $shiftEnd,
        public ?int $graceMinutes,
        public ?Carbon $graceEnd,
    ) {}
}
