<?php

namespace App\Http\Resources\Api\V1;

use App\Models\OrganizationSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrganizationSettings */
class OvertimeSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'overtime_enabled' => $this->overtime_enabled,
            'weekend_overtime_enabled' => $this->weekend_overtime_enabled,
            'holiday_overtime_enabled' => $this->holiday_overtime_enabled,
            'hourly_overtime_enabled' => $this->hourly_overtime_enabled,
            'overtime_full_day_minutes' => $this->overtime_full_day_minutes,
            'overtime_daily_salary_basis' => $this->overtime_daily_salary_basis->value,
            'overtime_hourly_rate_mode' => $this->overtime_hourly_rate_mode->value,
            'overtime_hourly_fixed_rate' => $this->overtime_hourly_fixed_rate,
            'overtime_hourly_multiplier' => $this->overtime_hourly_multiplier,
        ];
    }
}
