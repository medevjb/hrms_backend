<?php

namespace App\Http\Resources\Api\V1;

use App\Models\EmployeeSalary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmployeeSalary */
class EmployeeSalaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'effective_from' => $this->effective_from->toDateString(),
            'ended_at' => $this->ended_at?->toDateString(),
            'is_current' => $this->ended_at === null,
            'basic_salary' => (string) $this->basic_salary,
            'gross_monthly' => (string) $this->gross_monthly,
            'note' => $this->note,
            'components' => $this->whenLoaded('components', fn () => $this->components->map(fn ($component) => [
                'salary_component_id' => $component->salary_component_id,
                'code' => $component->component->code,
                'name' => $component->component->name,
                'type' => $component->component->type->value,
                'amount' => (string) $component->amount,
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
