<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ShiftOverride;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ShiftOverride */
class ShiftOverrideResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'work_date' => $this->work_date->toDateString(),
            'reason' => $this->reason,
            'changed_by' => $this->changed_by,
            'shift' => new ShiftResource($this->whenLoaded('shift')),
        ];
    }
}
