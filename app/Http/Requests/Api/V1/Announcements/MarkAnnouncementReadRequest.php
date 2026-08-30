<?php

namespace App\Http\Requests\Api\V1\Announcements;

use Illuminate\Foundation\Http\FormRequest;

/**
 * docs/PRD.md §57 — `acknowledge` is the explicit "I have read this" for
 * EMERGENCY / POLICY announcements; a plain open sends it false (or omits it).
 */
class MarkAnnouncementReadRequest extends FormRequest
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
            'acknowledge' => ['sometimes', 'boolean'],
        ];
    }
}
