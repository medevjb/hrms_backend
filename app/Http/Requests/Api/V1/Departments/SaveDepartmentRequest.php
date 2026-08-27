<?php

namespace App\Http\Requests\Api\V1\Departments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isCreate = $this->isMethod('post');

        return [
            'name' => [
                $isCreate ? 'required' : 'sometimes', 'string', 'max:150',
                Rule::unique('departments', 'name')->ignore($this->route('department')),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'operation_manager_id' => ['nullable', 'integer', 'exists:employees,id'],
        ];
    }
}
