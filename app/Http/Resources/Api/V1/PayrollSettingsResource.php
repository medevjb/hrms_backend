<?php

namespace App\Http\Resources\Api\V1;

use App\Models\OrganizationSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrganizationSettings */
class PayrollSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'payroll_cutoff_day' => $this->payroll_cutoff_day,
            'salary_day_calculation_method' => $this->salary_day_calculation_method->value,
        ];
    }
}
