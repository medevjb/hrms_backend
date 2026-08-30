<?php

namespace App\Http\Requests\Api\V1\Payroll;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * docs/PRD.md §146 — a manual arrear against a closed period. A negative
 * amount is a recovery and requires payroll.adjust (checked in the policy).
 */
class CreateArrearRequest extends FormRequest
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
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'original_period_id' => ['required', 'integer', Rule::exists('payroll_periods', 'id')],
            'amount' => ['required', 'numeric', 'not_in:0'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
