<?php

namespace App\Http\Requests\Api\V1\Shifts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateShiftOverrideRequest extends FormRequest
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
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'work_date' => [
                'required', 'date',
                Rule::unique('shift_overrides')->where('employee_id', $this->input('employee_id')),
            ],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
