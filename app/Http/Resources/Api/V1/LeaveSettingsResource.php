<?php

namespace App\Http\Resources\Api\V1;

use App\Models\OrganizationSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrganizationSettings */
class LeaveSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'leave_year_start_month' => $this->leave_year_start_month,
            'leave_carry_forward_cap_days' => $this->leave_carry_forward_cap_days,
        ];
    }
}
