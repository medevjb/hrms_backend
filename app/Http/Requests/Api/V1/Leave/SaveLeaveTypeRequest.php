<?php

namespace App\Http\Requests\Api\V1\Leave;

use App\Enums\LeaveAccrualMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveLeaveTypeRequest extends FormRequest
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
        $isCreate = $this->isMethod('post');
        $required = $isCreate ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:100'],
            'code' => [$required, 'string', 'max:50', Rule::unique('leave_types', 'code')->ignore($this->route('leaveType'))],
            'annual_allocation_days' => [$required, 'numeric', 'min:0', 'max:365'],
            'is_paid' => ['sometimes', 'boolean'],
            'supports_half_day' => ['sometimes', 'boolean'],
            'carry_forward_enabled' => ['sometimes', 'boolean'],
            'carry_forward_cap_days' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'requires_document' => ['sometimes', 'boolean'],
            'max_consecutive_days' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'min_employment_days' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'accrual_mode' => ['sometimes', Rule::enum(LeaveAccrualMode::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
