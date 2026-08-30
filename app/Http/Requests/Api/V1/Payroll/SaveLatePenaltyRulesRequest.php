<?php

namespace App\Http\Requests\Api\V1\Payroll;

use App\Enums\LatePenaltyDeductionMode;
use App\Enums\LatePenaltyOutcome;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * docs/PRD.md §61 — replaces the late-penalty policy with a new version:
 * an effective date and a set of tiers. A DEDUCTION tier needs a mode and
 * value; a WARNING tier does not.
 */
class SaveLatePenaltyRulesRequest extends FormRequest
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
            'effective_from' => ['required', 'date'],
            'tiers' => ['required', 'array', 'min:1'],
            'tiers.*.late_days_threshold' => ['required', 'integer', 'min:1'],
            'tiers.*.outcome' => ['required', Rule::enum(LatePenaltyOutcome::class)],
            'tiers.*.deduction_mode' => ['nullable', 'required_if:tiers.*.outcome,DEDUCTION', Rule::enum(LatePenaltyDeductionMode::class)],
            'tiers.*.deduction_value' => ['nullable', 'required_if:tiers.*.outcome,DEDUCTION', 'numeric', 'min:0'],
        ];
    }
}
