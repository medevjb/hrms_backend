<?php

namespace App\Http\Requests\Api\V1\Employees;

use App\Enums\EmployeeStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeStatusRequest extends FormRequest
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
            'status' => ['required', Rule::enum(EmployeeStatus::class)],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
