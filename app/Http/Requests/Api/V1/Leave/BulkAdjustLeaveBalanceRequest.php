<?php

namespace App\Http\Requests\Api\V1\Leave;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkAdjustLeaveBalanceRequest extends FormRequest
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
        $mode = $this->input('mode');

        return [
            'leave_type_id' => ['required', 'integer', Rule::exists('leave_types', 'id')->where('is_active', true)],
            'mode' => ['required', Rule::in(['GRANT', 'SET', 'REAPPLY_DEFAULT'])],
            'amount' => [
                Rule::requiredIf(fn () => in_array($mode, ['GRANT', 'SET'], true)),
                'nullable',
                'numeric',
                $mode === 'SET' ? 'min:0' : 'gt:-3650',
                'lt:3650',
            ],
            'note' => ['required', 'string', 'max:255'],
        ];
    }
}
