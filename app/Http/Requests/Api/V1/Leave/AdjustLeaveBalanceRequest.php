<?php

namespace App\Http\Requests\Api\V1\Leave;

use Illuminate\Foundation\Http\FormRequest;

class AdjustLeaveBalanceRequest extends FormRequest
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
            'amount' => ['required', 'numeric'],
            'note' => ['required', 'string', 'max:255'],
        ];
    }
}
