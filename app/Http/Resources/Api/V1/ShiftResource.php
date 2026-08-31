<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/** @mixin Shift */
class ShiftResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            // §139.4 — "09:00", no seconds; start_time/end_time come back
            // from MySQL's TIME column as "09:00:00".
            'start_time' => Carbon::parse($this->start_time)->format('H:i'),
            'end_time' => Carbon::parse($this->end_time)->format('H:i'),
            'expected_work_minutes' => $this->expected_work_minutes,
            'break_minutes' => $this->break_minutes,
            'break_start' => $this->break_start ? Carbon::parse($this->break_start)->format('H:i') : null,
            'break_end' => $this->break_end ? Carbon::parse($this->break_end)->format('H:i') : null,
            'late_grace_minutes' => $this->late_grace_minutes,
            'is_overnight' => $this->isOvernight(),
            'active' => $this->active,
        ];
    }
}
