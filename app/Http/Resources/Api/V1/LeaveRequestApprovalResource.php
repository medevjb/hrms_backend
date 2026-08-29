<?php

namespace App\Http\Resources\Api\V1;

use App\Models\LeaveRequestApproval;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LeaveRequestApproval */
class LeaveRequestApprovalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stage' => $this->stage->value,
            'approver' => [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
            ],
            'decision' => $this->decision->value,
            'reason' => $this->reason,
            'decided_at' => $this->decided_at->toIso8601String(),
        ];
    }
}
