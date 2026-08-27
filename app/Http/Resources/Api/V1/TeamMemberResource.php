<?php

namespace App\Http\Resources\Api\V1;

use App\Models\TeamMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TeamMember */
class TeamMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee' => [
                'id' => $this->employee->id,
                'full_name' => $this->employee->fullName(),
                'employee_code' => $this->employee->employee_code,
            ],
            'started_at' => $this->started_at->toDateString(),
            'ended_at' => $this->ended_at?->toDateString(),
        ];
    }
}
