<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Self-service profile edits, open to every authenticated user (no
 * permission). Only the fields a person owns about themselves — their
 * name and contact details. Everything HR controls (designation, team,
 * shift, employment type, status, dates, salary) stays on
 * PUT /employees/{id} and is silently ignored here.
 *
 * An employee sends first_name/last_name; a bare user account (an admin
 * with no employee record) sends the single display `name`.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required_without_all:first_name,last_name', 'string', 'max:255'],
            'first_name' => ['required_with:last_name', 'string', 'max:100'],
            'last_name' => ['required_with:first_name', 'string', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}
