<?php

namespace App\Http\Resources\Api\V1;

use App\Models\OrganizationSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrganizationSettings */
class AttendanceSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'late_grace_minutes' => $this->late_grace_minutes,
            'auto_absent_enabled' => $this->auto_absent_enabled,
            'missing_checkout_policy' => $this->missing_checkout_policy->value,
            'attendance_min_minutes_half_day' => $this->attendance_min_minutes_half_day,
            'attendance_checkin_window_minutes' => $this->attendance_checkin_window_minutes,
        ];
    }
}
