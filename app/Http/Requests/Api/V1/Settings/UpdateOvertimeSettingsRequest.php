<?php

namespace App\Http\Requests\Api\V1\Settings;

use App\Enums\OvertimeDailySalaryBasis;
use App\Enums\OvertimeHourlyRateMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOvertimeSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policy check happens in the controller
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'overtime_enabled' => ['sometimes', 'boolean'],
            'weekend_overtime_enabled' => ['sometimes', 'boolean'],
            'holiday_overtime_enabled' => ['sometimes', 'boolean'],
            'hourly_overtime_enabled' => ['sometimes', 'boolean'],
            'overtime_full_day_minutes' => ['sometimes', 'integer', 'min:1'],
            'overtime_daily_salary_basis' => ['sometimes', Rule::enum(OvertimeDailySalaryBasis::class)],
            'overtime_hourly_rate_mode' => ['sometimes', Rule::enum(OvertimeHourlyRateMode::class)],
            'overtime_hourly_fixed_rate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'overtime_hourly_multiplier' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
