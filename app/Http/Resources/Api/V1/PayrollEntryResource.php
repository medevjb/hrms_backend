<?php

namespace App\Http\Resources\Api\V1;

use App\Models\PayrollEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PayrollEntry */
class PayrollEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payroll_period_id' => $this->payroll_period_id,
            'status' => $this->status->value,
            'employee' => [
                'id' => $this->employee->id,
                'full_name' => $this->employee->fullName(),
                'employee_code' => $this->employee->employee_code,
            ],
            'period' => $this->whenLoaded('period', fn () => [
                'id' => $this->period->id,
                'label' => $this->period->label,
                'status' => $this->period->status->value,
                'start_date' => $this->period->start_date->toDateString(),
                'end_date' => $this->period->end_date->toDateString(),
            ]),
            'basic_salary' => (string) $this->basic_salary,
            'daily_salary' => (string) $this->daily_salary,
            'period_days' => $this->period_days,
            'late_days' => (string) $this->late_days,
            'absent_days' => (string) $this->absent_days,
            'unpaid_leave_days' => (string) $this->unpaid_leave_days,
            'overtime_days' => (string) $this->overtime_days,
            'gross_earnings' => (string) $this->gross_earnings,
            'total_deductions' => (string) $this->total_deductions,
            'net_salary' => (string) $this->net_salary,
            'calculated_at' => $this->calculated_at?->toIso8601String(),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line) => [
                'id' => $line->id,
                'category' => $line->category->value,
                'type' => $line->type->value,
                'label' => $line->label,
                'amount' => (string) $line->amount,
                'is_manual' => $line->is_manual,
                'computed_from' => $line->computed_from,
            ])->values()),
            'adjustments' => $this->whenLoaded('adjustments', fn () => $this->adjustments->map(fn ($adjustment) => [
                'id' => $adjustment->id,
                'type' => $adjustment->type->value,
                'label' => $adjustment->label,
                'amount' => (string) $adjustment->amount,
                'reason' => $adjustment->reason,
                'created_at' => $adjustment->created_at?->toIso8601String(),
            ])->values()),
        ];
    }
}
