<?php

namespace App\Http\Requests\Api\V1\Payroll;

use App\Enums\PayrollAdjustmentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * docs/PRD.md §68 — a manual earning / deduction / bonus / penalty waiver.
 * The reason is mandatory; the amount arrives as a string (§141).
 */
class AdjustPayrollEntryRequest extends FormRequest
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
            'type' => ['required', Rule::enum(PayrollAdjustmentType::class)],
            'label' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
