<?php

namespace App\Http\Requests\Api\V1\Employees;

use Illuminate\Foundation\Http\FormRequest;

class TransferEmployeeRequest extends FormRequest
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
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'effective_date' => ['nullable', 'date'],
        ];
    }
}
