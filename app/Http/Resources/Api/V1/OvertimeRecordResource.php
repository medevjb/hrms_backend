<?php

namespace App\Http\Resources\Api\V1;

use App\Models\OvertimeRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OvertimeRecord */
class OvertimeRecordResource extends JsonResource
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
            'attendance_record_id' => $this->attendance_record_id,
            'work_date' => $this->work_date->toDateString(),
            'type' => $this->type->value,
            'worked_minutes' => $this->worked_minutes,
            'full_day_minutes_used' => $this->full_day_minutes_used,
            'overtime_days' => (float) $this->overtime_days,
            'manual_days_override' => $this->manual_days_override !== null ? (float) $this->manual_days_override : null,
            'effective_overtime_days' => $this->effectiveOvertimeDays(),
            'status' => $this->status->value,
            'current_stage' => $this->current_stage?->value,
            'rejection_reason' => $this->rejection_reason,
            'manual_adjustment_reason' => $this->manual_adjustment_reason,
            'adjusted_at' => $this->adjusted_at?->toIso8601String(),
            'decided_at' => $this->decided_at?->toIso8601String(),
            'payroll_processed_at' => $this->payroll_processed_at?->toIso8601String(),
            'approvals' => OvertimeApprovalResource::collection($this->whenLoaded('approvals')),
        ];
    }
}
