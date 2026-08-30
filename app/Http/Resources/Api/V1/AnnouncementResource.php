<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Announcement */
class AnnouncementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'title' => $this->title,
            'content' => $this->content,
            'audience_type' => $this->audience_type->value,
            'status' => $this->status->value,
            'acknowledgement_required' => $this->acknowledgement_required,
            'attachment_path' => $this->attachment_path,
            'publish_at' => $this->publish_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_by' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'targets' => $this->whenLoaded('targets', fn () => $this->targets->map(fn ($target) => [
                'target_type' => $target->target_type->value,
                'target_id' => $target->target_id,
            ])),
            'read_count' => $this->whenCounted('reads'),
            'acknowledged_count' => $this->whenCounted('acknowledged_reads'),
            'my_read' => $this->when(
                $this->relationLoaded('reads'),
                fn () => $this->reads->first() ? [
                    'acknowledged' => $this->reads->first()->acknowledged,
                    'read_at' => $this->reads->first()->read_at->toIso8601String(),
                ] : null,
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
