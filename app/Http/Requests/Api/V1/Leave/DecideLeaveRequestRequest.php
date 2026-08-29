<?php

namespace App\Http\Requests\Api\V1\Leave;

use Illuminate\Foundation\Http\FormRequest;

class DecideLeaveRequestRequest extends FormRequest
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
        $reasonRequired = $this->routeIs('*.reject') || $this->routeIs('*.direct-approve');

        return [
            'reason' => [$reasonRequired ? 'required' : 'nullable', 'string', 'max:1000'],
        ];
    }
}
