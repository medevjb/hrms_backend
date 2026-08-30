<?php

namespace App\Http\Requests\Api\V1\Payroll;

use App\Enums\SalaryComponentType;
use App\Models\SalaryComponent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * docs/PRD.md §59 — a salary component: code, name, type, and whether it's
 * active. `code` is unique and immutable on update (payroll snapshots
 * reference it).
 */
class SaveSalaryComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // permission check happens in the controller
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isCreate = $this->isMethod('post');
        $component = $this->route('salaryComponent');
        $componentId = $component instanceof SalaryComponent ? $component->id : null;

        return [
            'code' => [
                $isCreate ? 'required' : 'prohibited',
                'string', 'max:40', 'alpha_dash',
                Rule::unique('salary_components', 'code')->ignore($componentId),
            ],
            'name' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:100'],
            'type' => [$isCreate ? 'required' : 'sometimes', Rule::enum(SalaryComponentType::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:99'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
