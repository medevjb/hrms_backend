<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * What the nightly attendance close job (docs/PRD.md §137) did for one
 * work_date — the payload behind its single HR notification, not one per
 * employee.
 */
readonly class AttendanceCloseSummary
{
    public function __construct(
        public Carbon $workDate,
        public int $absent = 0,
        public int $missingCheckout = 0,
        public int $halfDay = 0,
        public int $weekend = 0,
        public int $holiday = 0,
        public int $onLeave = 0,
        public int $unchanged = 0,
        public int $skippedManualAdjustment = 0,
    ) {}

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'work_date' => $this->workDate->toDateString(),
            'absent' => $this->absent,
            'missing_checkout' => $this->missingCheckout,
            'half_day' => $this->halfDay,
            'weekend' => $this->weekend,
            'holiday' => $this->holiday,
            'on_leave' => $this->onLeave,
            'unchanged' => $this->unchanged,
            'skipped_manual_adjustment' => $this->skippedManualAdjustment,
        ];
    }
}
