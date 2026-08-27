<?php

namespace App\Http\Requests\Api\V1\Settings;

use App\Enums\MissingCheckoutPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceSettingsRequest extends FormRequest
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
            'late_grace_minutes' => ['sometimes', 'integer', 'min:0', 'max:120'],
            'auto_absent_enabled' => ['sometimes', 'boolean'],
            'missing_checkout_policy' => ['sometimes', Rule::enum(MissingCheckoutPolicy::class)],
            'attendance_min_minutes_half_day' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
