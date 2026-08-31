<?php

namespace App\Http\Requests\Api\V1\Shifts;

use Illuminate\Foundation\Http\FormRequest;

class SaveShiftRequest extends FormRequest
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
        $isCreate = $this->isMethod('post');
        $required = $isCreate ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:100'],
            'start_time' => [$required, 'date_format:H:i'],
            'end_time' => [$required, 'date_format:H:i'],
            'expected_work_minutes' => [$required, 'integer', 'min:1'],
            'break_minutes' => ['sometimes', 'integer', 'min:0'],
            'break_start' => ['sometimes', 'nullable', 'date_format:H:i', 'required_with:break_end'],
            'break_end' => ['sometimes', 'nullable', 'date_format:H:i', 'required_with:break_start', 'after:break_start'],
            // null = use organization_settings.late_grace_minutes (§16-§22).
            'late_grace_minutes' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
