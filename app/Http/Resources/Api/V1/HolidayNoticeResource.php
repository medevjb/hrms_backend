<?php

namespace App\Http\Resources\Api\V1;

use App\Models\HolidayNotice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HolidayNotice */
class HolidayNoticeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'title' => $this->title,
            'message' => $this->message,
            'closure_note' => $this->closure_note,
            'return_date' => $this->return_date?->toDateString(),
            'signatory_name' => $this->signatory_name,
            'generated_at' => $this->generated_at?->toIso8601String(),
            'has_document' => $this->file_path !== null,
            'announcement_id' => $this->announcement_id,
            'holiday' => [
                'id' => $this->holiday->id,
                'title' => $this->holiday->title,
                'date' => $this->holiday->date->toDateString(),
                'type' => $this->holiday->type->value,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
