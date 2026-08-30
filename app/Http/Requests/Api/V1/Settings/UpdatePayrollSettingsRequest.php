<?php

namespace App\Http\Requests\Api\V1\Settings;

use App\Enums\SalaryDayCalculationMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePayrollSettingsRequest extends FormRequest
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
            'payroll_cutoff_day' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:28'],
            'salary_day_calculation_method' => ['sometimes', Rule::enum(SalaryDayCalculationMethod::class)],
            'late_penalty_enabled' => ['sometimes', 'boolean'],
            'absence_deduction_enabled' => ['sometimes', 'boolean'],
            'unpaid_leave_deduction_enabled' => ['sometimes', 'boolean'],
            'overtime_earnings_enabled' => ['sometimes', 'boolean'],
            'dispute_window_days' => ['sometimes', 'integer', 'min:1', 'max:60'],
        ];
    }
}
