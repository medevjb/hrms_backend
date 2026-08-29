<?php

namespace App\Http\Requests\Api\V1\Overtime;

use Illuminate\Foundation\Http\FormRequest;

class AdjustOvertimeRequest extends FormRequest
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
            'overtime_days' => ['required', 'numeric', 'min:0', 'max:2'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
