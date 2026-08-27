<?php

namespace App\Http\Requests\Api\V1\Employees;

use App\Enums\EmploymentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
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
        return [
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'designation' => ['sometimes', 'string', 'max:100'],
            'employment_type' => ['sometimes', Rule::enum(EmploymentType::class)],
            'confirmation_date' => ['nullable', 'date'],
            'office_location' => ['nullable', 'string', 'max:100'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'overtime_eligible' => ['sometimes', 'boolean'],
        ];
    }
}
