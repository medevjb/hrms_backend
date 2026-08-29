<?php

namespace App\Http\Requests\Api\V1\Overtime;

use Illuminate\Foundation\Http\FormRequest;

class DecideOvertimeRequest extends FormRequest
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
        $reasonRequired = $this->routeIs('*.reject');

        return [
            'reason' => [$reasonRequired ? 'required' : 'nullable', 'string', 'max:1000'],
        ];
    }
}
