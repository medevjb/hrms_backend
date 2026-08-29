<?php

namespace App\Http\Requests\Api\V1\Leave;

use App\Enums\HalfDayPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitLeaveRequestRequest extends FormRequest
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
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_half_day' => ['sometimes', 'boolean'],
            'half_day_period' => ['required_if:is_half_day,true', Rule::enum(HalfDayPeriod::class)],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
