<?php

namespace App\Http\Resources\Api\V1;

use App\Support\AttendanceToday;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps an AttendanceToday value object — not an Eloquent model, but
 * JsonResource wraps any object fine, and its magic __get proxies straight
 * to the wrapped object's public readonly properties.
 *
 * @mixin AttendanceToday
 */
class AttendanceTodayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $shift = $this->shift;

        return [
            'work_date' => $this->workDate->toDateString(),
            'is_work_day' => $shift->isWorkDay,
            'is_weekend' => $shift->isWeekend,
            'is_holiday' => $shift->isHoliday,
            'has_approved_leave' => $this->hasApprovedLeave,
            'shift' => $shift->shift ? ['id' => $shift->shift->id, 'name' => $shift->shift->name] : null,
            'shift_start' => $shift->shiftStart?->toIso8601String(),
            'shift_end' => $shift->shiftEnd?->toIso8601String(),
            'grace_end' => $shift->graceEnd?->toIso8601String(),
            'should_prompt_check_in' => $this->shouldPromptCheckIn,
            'record' => $this->record ? new AttendanceRecordResource($this->record) : null,
        ];
    }
}
