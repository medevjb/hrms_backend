<?php

namespace App\Http\Requests\Api\V1\Payroll;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * docs/PRD.md §59 — a new effective-dated salary version: an effective
 * date and an amount per salary component. Amounts arrive as strings (§141
 * — money is never a JSON number).
 */
class AssignSalaryRequest extends FormRequest
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
            'effective_from' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
            'components' => ['required', 'array', 'min:1'],
            'components.*.salary_component_id' => ['required', 'integer', Rule::exists('salary_components', 'id')],
            'components.*.amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
