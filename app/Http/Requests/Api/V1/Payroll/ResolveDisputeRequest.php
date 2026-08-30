<?php

namespace App\Http\Requests\Api\V1\Payroll;

use App\Enums\PayrollDisputeResolution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * docs/PRD.md §147 — a resolution needs an outcome and an explanation
 * ("a dispute resolved without an explanation is not resolved").
 */
class ResolveDisputeRequest extends FormRequest
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
            'resolution' => ['required', Rule::enum(PayrollDisputeResolution::class)],
            'note' => ['required', 'string', 'max:2000'],
        ];
    }
}
