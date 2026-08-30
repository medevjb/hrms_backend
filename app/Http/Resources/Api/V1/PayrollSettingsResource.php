<?php

namespace App\Http\Resources\Api\V1;

use App\Models\OrganizationSettings;
use App\Models\PayrollSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * docs/PRD.md §63/§65/§66 — the payroll configuration screen combines the
 * cutoff/salary-day fields that live on OrganizationSettings (§85) with the
 * PayrollSettings toggles.
 *
 * @mixin OrganizationSettings
 */
class PayrollSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payroll = PayrollSettings::current();

        return [
            'payroll_cutoff_day' => $this->payroll_cutoff_day,
            'salary_day_calculation_method' => $this->salary_day_calculation_method->value,
            'late_penalty_enabled' => $payroll->late_penalty_enabled,
            'absence_deduction_enabled' => $payroll->absence_deduction_enabled,
            'unpaid_leave_deduction_enabled' => $payroll->unpaid_leave_deduction_enabled,
            'overtime_earnings_enabled' => $payroll->overtime_earnings_enabled,
            'dispute_window_days' => $payroll->dispute_window_days,
        ];
    }
}
