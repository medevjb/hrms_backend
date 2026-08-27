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
        ];
    }
}
