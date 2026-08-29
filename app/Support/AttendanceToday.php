<?php

namespace App\Support;

use App\Models\AttendanceRecord;
use Illuminate\Support\Carbon;

/**
 * The resolved context `GET /api/v1/attendance/today` returns — everything
 * the frontend needs to decide whether to show the check-in popup (§26)
 * without deciding it itself (§4, §137's "check-in prompt suppression").
 */
readonly class AttendanceToday
{
    public function __construct(
        public Carbon $workDate,
        public ShiftResolution $shift,
        public bool $hasApprovedLeave,
        public bool $shouldPromptCheckIn,
        public ?AttendanceRecord $record,
    ) {}
}
