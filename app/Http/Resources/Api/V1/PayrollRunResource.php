<?php

namespace App\Http\Resources\Api\V1;

use App\Models\PayrollRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PayrollRun */
class PayrollRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payroll_period_id' => $this->payroll_period_id,
            'sequence' => $this->sequence,
            'entry_count' => $this->entry_count,
            'gross_total' => (string) $this->gross_total,
            'deduction_total' => (string) $this->deduction_total,
            'net_total' => (string) $this->net_total,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
