<?php

namespace App\Http\Requests\Api\V1\Attendance;

use App\Enums\AttendanceStatus;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustAttendanceRequest extends FormRequest
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
            'check_in' => ['sometimes', 'nullable', 'date'],
            'check_out' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', Rule::enum(AttendanceStatus::class)],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            if (! $this->hasAny(['check_in', 'check_out', 'status'])) {
                $validator->errors()->add('check_in', 'At least one of check_in, check_out, or status must be provided.');
            }
        });
    }
}
