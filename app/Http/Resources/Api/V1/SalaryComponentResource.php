<?php

namespace App\Http\Resources\Api\V1;

use App\Models\SalaryComponent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SalaryComponent */
class SalaryComponentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type->value,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}
