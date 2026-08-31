<?php

namespace App\Support;

use App\Models\OrganizationSettings;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

/**
 * Pushes the organization's stored SMTP settings (§85) into Laravel's mail
 * config so every outbound message uses them. Applied once at boot and
 * again right before the admin console's "send test email" so a just-saved
 * change takes effect without a redeploy.
 */
class OrganizationMailConfig
{
    public static function apply(?OrganizationSettings $settings = null): void
    {
        // Boot runs before migrations on a fresh install — never fail here.
        if (! Schema::hasTable('organization_settings')) {
            return;
        }

        // Read an existing row only — do NOT create the singleton as a side
        // effect of booting (tests assert on its absence; §85's create path
        // is current(), reached the first time an admin opens settings).
        $settings ??= OrganizationSettings::query()->first();

        if ($settings === null) {
            return;
        }

        if ($settings->mail_from_name || $settings->mail_from_address) {
            Config::set('mail.from.name', $settings->mail_from_name ?: config('mail.from.name'));
            Config::set('mail.from.address', $settings->mail_from_address ?: config('mail.from.address'));
        }

        if (! $settings->hasCustomMailer()) {
            return;
        }

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', $settings->mail_host);
        Config::set('mail.mailers.smtp.port', $settings->mail_port ?? 587);
        Config::set('mail.mailers.smtp.username', $settings->mail_username);
        Config::set('mail.mailers.smtp.password', $settings->mail_password);
        Config::set('mail.mailers.smtp.encryption', $settings->mail_encryption);
    }
}
