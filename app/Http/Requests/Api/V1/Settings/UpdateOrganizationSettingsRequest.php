<?php

namespace App\Http\Requests\Api\V1\Settings;

use App\Enums\Weekday;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationSettingsRequest extends FormRequest
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
            'company_name' => ['sometimes', 'string', 'max:150'],
            'company_logo_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'timezone' => ['sometimes', 'timezone'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'currency_decimal_places' => ['sometimes', 'integer', 'min:0', 'max:4'],
            'weekend_days' => ['sometimes', 'array', 'min:0', 'max:7'],
            'weekend_days.*' => ['string', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'default_weekend_day' => ['sometimes', Rule::enum(Weekday::class)],
            'default_shift_id' => ['sometimes', 'nullable', 'integer', 'exists:shifts,id'],
        ];
    }
}
