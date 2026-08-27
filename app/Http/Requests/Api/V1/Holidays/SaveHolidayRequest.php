<?php

namespace App\Http\Requests\Api\V1\Holidays;

use App\Enums\HolidayType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveHolidayRequest extends FormRequest
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
            'title' => [$required, 'string', 'max:150'],
            'date' => [$required, 'date'],
            'type' => [$required, Rule::enum(HolidayType::class)],
            'description' => ['nullable', 'string', 'max:255'],
            'office_location' => ['nullable', 'string', 'max:100'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
