<?php

namespace App\Http\Requests\Api\V1\PersonalEvents;

use App\Models\PersonalEvent;
use Illuminate\Foundation\Http\FormRequest;

class SavePersonalEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ownership check happens in the controller
    }

    /**
     * On a partial update, backfill whichever date was left out so the
     * end >= start rule always has both sides to compare.
     */
    protected function prepareForValidation(): void
    {
        $event = $this->route('personalEvent');

        if ($event instanceof PersonalEvent) {
            $this->merge([
                'start_date' => $this->input('start_date', $event->start_date->toDateString()),
                'end_date' => $this->input('end_date', $event->end_date->toDateString()),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'start_date' => [$required, 'date'],
            'end_date' => [$required, 'date', 'after_or_equal:start_date'],
        ];
    }
}
