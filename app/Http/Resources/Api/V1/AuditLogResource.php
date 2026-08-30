<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuditLog */
class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action->value,
            'entity_type' => $this->entity_type !== null ? class_basename($this->entity_type) : null,
            'entity_id' => $this->entity_id,
            'old_data' => $this->old_data,
            'new_data' => $this->new_data,
            'reason' => $this->reason,
            'ip_address' => $this->ip_address,
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
