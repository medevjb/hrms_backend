<?php

namespace App\Http\Resources\Api\V1;

use App\Models\OrganizationSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrganizationSettings */
class OrganizationSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'company_name' => $this->company_name,
            'company_logo_path' => $this->company_logo_path,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'currency_decimal_places' => $this->currency_decimal_places,
            'weekend_days' => $this->weekend_days,
            'default_weekend_day' => $this->default_weekend_day?->value,
            'default_shift_id' => $this->default_shift_id,
        ];
    }
}
