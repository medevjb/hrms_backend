<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * What OvertimeService::detectForWorkDate() did for one work_date, run
 * right after the nightly attendance close (docs/PRD.md §137, §52).
 * `detected` entered the approval chain; `rejectedInsufficientDuration`
 * were recorded but auto-rejected under §46 (half-day overtime OFF for V1).
 */
readonly class OvertimeDetectionSummary
{
    public function __construct(
        public Carbon $workDate,
        public int $detected = 0,
        public int $rejectedInsufficientDuration = 0,
        public int $skipped = 0,
    ) {}

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'work_date' => $this->workDate->toDateString(),
            'detected' => $this->detected,
            'rejected_insufficient_duration' => $this->rejectedInsufficientDuration,
            'skipped' => $this->skipped,
        ];
    }
}
