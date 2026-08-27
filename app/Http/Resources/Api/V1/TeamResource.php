<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Team */
class TeamResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'active' => $this->active,
            'department' => ['id' => $this->department->id, 'name' => $this->department->name],
            'team_leader' => $this->teamLeader ? [
                'id' => $this->teamLeader->id,
                'full_name' => $this->teamLeader->fullName(),
            ] : null,
            'member_count' => $this->whenCounted('currentTeamMembers'),
        ];
    }
}
