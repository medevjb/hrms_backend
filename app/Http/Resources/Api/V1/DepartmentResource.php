<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Department */
class DepartmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'active' => $this->active,
            'operation_manager' => $this->operationManager ? [
                'id' => $this->operationManager->id,
                'full_name' => $this->operationManager->fullName(),
            ] : null,
        ];
    }
}
