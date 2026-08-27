<?php

namespace App\Http\Resources\Api\V1;

use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UserRole */
class UserRoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'role' => [
                'id' => $this->role->id,
                'name' => $this->role->name,
            ],
            'scope' => $this->scope->value,
            'scope_id' => $this->scope_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
