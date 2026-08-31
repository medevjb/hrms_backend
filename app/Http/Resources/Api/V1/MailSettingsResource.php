<?php

namespace App\Http\Resources\Api\V1;

use App\Models\OrganizationSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Outbound-mail configuration for the admin console. The password is never
 * echoed back — only whether one is stored, so the form can show a "leave
 * blank to keep" affordance (docs/PRD.md §139.2 mindset: don't leak
 * secrets through a settings read).
 *
 * @mixin OrganizationSettings
 */
class MailSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'mail_from_name' => $this->mail_from_name,
            'mail_from_address' => $this->mail_from_address,
            'mail_host' => $this->mail_host,
            'mail_port' => $this->mail_port,
            'mail_username' => $this->mail_username,
            'mail_encryption' => $this->mail_encryption,
            'mail_password_set' => filled($this->mail_password),
            'is_active' => $this->hasCustomMailer(),
        ];
    }
}
