<?php

namespace App\Http\Resources\Api\V1;

use App\Models\LeaveBalance;
use App\Services\LeaveBalanceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LeaveBalance */
class LeaveBalanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // `taken` is the real ledger figure (approvals net of cancellations);
        // `entitlement` is derived so the row always reconciles
        // (allocated − taken = balance). A prorated mid-year joiner then
        // reads "9 of 9, 0 taken", not "9 of 10, 1 taken".
        $taken = app(LeaveBalanceService::class)->daysTakenFor($this->resource);

        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'leave_type' => [
                'id' => $this->leaveType->id,
                'name' => $this->leaveType->name,
                'code' => $this->leaveType->code,
            ],
            'leave_year' => $this->leave_year,
            'balance' => (float) $this->balance,
            'taken' => $taken,
            'entitlement' => (float) $this->balance + $taken,
        ];
    }
}
