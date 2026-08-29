<?php

namespace App\Http\Requests\Api\V1\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeaveSettingsRequest extends FormRequest
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
            'leave_year_start_month' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'leave_carry_forward_cap_days' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
