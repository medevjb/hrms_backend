<?php

namespace App\Http\Resources\Api\V1;

use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LeaveType */
class LeaveTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'annual_allocation_days' => (float) $this->annual_allocation_days,
            'is_paid' => $this->is_paid,
            'supports_half_day' => $this->supports_half_day,
            'carry_forward_enabled' => $this->carry_forward_enabled,
            'carry_forward_cap_days' => $this->carry_forward_cap_days !== null ? (float) $this->carry_forward_cap_days : null,
            'requires_document' => $this->requires_document,
            'max_consecutive_days' => $this->max_consecutive_days,
            'min_employment_days' => $this->min_employment_days,
            'accrual_mode' => $this->accrual_mode->value,
            'is_active' => $this->is_active,
        ];
    }
}
