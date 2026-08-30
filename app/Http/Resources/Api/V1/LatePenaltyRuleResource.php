<?php

namespace App\Http\Resources\Api\V1;

use App\Models\LatePenaltyRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LatePenaltyRule */
class LatePenaltyRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'effective_from' => $this->effective_from->toDateString(),
            'late_days_threshold' => $this->late_days_threshold,
            'outcome' => $this->outcome->value,
            'deduction_mode' => $this->deduction_mode?->value,
            'deduction_value' => $this->deduction_value !== null ? (string) $this->deduction_value : null,
        ];
    }
}
