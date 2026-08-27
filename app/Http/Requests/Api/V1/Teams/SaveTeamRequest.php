<?php

namespace App\Http\Requests\Api\V1\Teams;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTeamRequest extends FormRequest
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
        $isCreate = $this->isMethod('post');
        /** @var Team|null $team */
        $team = $this->route('team');
        $departmentId = $this->input('department_id') ?? $team?->department_id;

        return [
            'department_id' => [$isCreate ? 'required' : 'sometimes', 'integer', 'exists:departments,id'],
            'name' => [
                $isCreate ? 'required' : 'sometimes', 'string', 'max:150',
                Rule::unique('teams', 'name')
                    ->where(fn ($query) => $query->where('department_id', $departmentId))
                    ->ignore($team),
            ],
            'team_leader_id' => ['nullable', 'integer', 'exists:employees,id'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
