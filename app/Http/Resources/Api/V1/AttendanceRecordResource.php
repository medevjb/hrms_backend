<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AttendanceRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * §99's attendance report columns, plus the raw fields §32 corrections and
 * §95 history need. grace_end_time is derived here (shift_start_used +
 * grace_minutes_used), not stored — it's arithmetic on two values that are
 * themselves already frozen snapshots, so there's nothing to re-derive
 * incorrectly later.
 *
 * @mixin AttendanceRecord
 */
class AttendanceRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee' => [
                'id' => $this->employee->id,
                'full_name' => $this->employee->fullName(),
                'employee_code' => $this->employee->employee_code,
            ],
            'work_date' => $this->work_date->toDateString(),
            'shift' => $this->shift ? ['id' => $this->shift->id, 'name' => $this->shift->name] : null,
            'shift_start_used' => $this->shift_start_used?->toIso8601String(),
            'shift_end_used' => $this->shift_end_used?->toIso8601String(),
            'grace_minutes_used' => $this->grace_minutes_used,
            'grace_end_time' => $this->shift_start_used && $this->grace_minutes_used !== null
                ? $this->shift_start_used->copy()->addMinutes($this->grace_minutes_used)->toIso8601String()
                : null,
            'check_in' => $this->check_in?->toIso8601String(),
            'check_out' => $this->check_out?->toIso8601String(),
            'worked_minutes' => $this->worked_minutes,
            'late_minutes' => $this->late_minutes,
            'status' => $this->status->value,
            'is_manual_adjustment' => $this->is_manual_adjustment,
        ];
    }
}
