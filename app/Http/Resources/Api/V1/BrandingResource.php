<?php

namespace App\Http\Resources\Api\V1;

use App\Models\OrganizationSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The public face of the organization — company name, app title, and the
 * uploaded logo/favicon URLs. Served without a session so the sign-in
 * screen and the browser tab can render it (docs/PRD.md §85).
 *
 * @mixin OrganizationSettings
 */
class BrandingResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'company_name' => $this->company_name,
            'app_title' => $this->displayTitle(),
            'logo_url' => $this->logoUrl(),
            'favicon_url' => $this->faviconUrl(),
        ];
    }
}
