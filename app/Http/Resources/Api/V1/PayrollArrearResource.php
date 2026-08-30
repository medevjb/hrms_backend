<?php

namespace App\Http\Resources\Api\V1;

use App\Models\PayrollArrear;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PayrollArrear */
class PayrollArrearResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_type' => $this->source_type->value,
            'amount' => (string) $this->amount,
            'reason' => $this->reason,
            'status' => $this->status->value,
            'applied_at' => $this->applied_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'employee' => [
                'id' => $this->employee->id,
                'full_name' => $this->employee->fullName(),
                'employee_code' => $this->employee->employee_code,
            ],
            'original_period' => $this->originalPeriod->label,
        ];
    }
}
