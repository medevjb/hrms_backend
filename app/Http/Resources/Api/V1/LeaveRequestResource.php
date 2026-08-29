<?php

namespace App\Http\Resources\Api\V1;

use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LeaveRequest */
class LeaveRequestResource extends JsonResource
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
            'leave_type' => [
                'id' => $this->leaveType->id,
                'name' => $this->leaveType->name,
                'code' => $this->leaveType->code,
            ],
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'is_half_day' => $this->is_half_day,
            'half_day_period' => $this->half_day_period?->value,
            'days_requested' => (float) $this->days_requested,
            'reason' => $this->reason,
            'status' => $this->status->value,
            'current_stage' => $this->current_stage?->value,
            'required_stages' => $this->required_stages,
            'is_direct_approval' => $this->is_direct_approval,
            'direct_approval_reason' => $this->direct_approval_reason,
            'bypassed_stages' => $this->bypassed_stages,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'decided_at' => $this->decided_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'approvals' => LeaveRequestApprovalResource::collection($this->whenLoaded('approvals')),
        ];
    }
}
