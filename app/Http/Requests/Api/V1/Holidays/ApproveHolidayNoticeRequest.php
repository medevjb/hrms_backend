<?php

namespace App\Http\Requests\Api\V1\Holidays;

use Illuminate\Foundation\Http\FormRequest;

/**
 * docs/PRD.md §56 — Head HR may adjust the auto-drafted message, add
 * closure information, and set the return date before signing. All
 * optional: an unedited draft is a valid notice.
 */
class ApproveHolidayNoticeRequest extends FormRequest
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
            'message' => ['sometimes', 'string', 'max:5000'],
            'closure_note' => ['nullable', 'string', 'max:2000'],
            'return_date' => ['nullable', 'date'],
        ];
    }
}
