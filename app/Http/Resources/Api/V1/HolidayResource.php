<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Holiday;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Holiday */
class HolidayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'date' => $this->date->toDateString(),
            'type' => $this->type->value,
            'description' => $this->description,
            'office_location' => $this->office_location,
            'active' => $this->active,
        ];
    }
}
