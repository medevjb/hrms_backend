<?php

namespace App\Http\Resources\Api\V1;

use App\Models\PayrollPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PayrollPeriod */
class PayrollPeriodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'status' => $this->status->value,
            'cutoff_day_used' => $this->cutoff_day_used,
            'salary_day_calculation_method_used' => $this->salary_day_calculation_method_used->value,
            'processed_at' => $this->processed_at?->toIso8601String(),
            'finalized_at' => $this->finalized_at?->toIso8601String(),
            'entry_count' => $this->whenCounted('entries'),
            'net_total' => $this->when(
                isset($this->entries_sum_net_salary),
                fn () => (string) ($this->entries_sum_net_salary ?? '0.0000'),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
