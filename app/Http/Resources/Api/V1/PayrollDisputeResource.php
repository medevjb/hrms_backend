<?php

namespace App\Http\Resources\Api\V1;

use App\Models\PayrollDispute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PayrollDispute */
class PayrollDisputeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payroll_entry_id' => $this->payroll_entry_id,
            'status' => $this->status->value,
            'reason' => $this->reason,
            'resolution' => $this->resolution?->value,
            'resolution_note' => $this->resolution_note,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'entry' => $this->whenLoaded('entry', fn () => [
                'id' => $this->entry->id,
                'net_salary' => (string) $this->entry->net_salary,
                'employee' => [
                    'id' => $this->entry->employee->id,
                    'full_name' => $this->entry->employee->fullName(),
                    'employee_code' => $this->entry->employee->employee_code,
                ],
                'period' => $this->entry->period->label,
            ]),
        ];
    }
}
