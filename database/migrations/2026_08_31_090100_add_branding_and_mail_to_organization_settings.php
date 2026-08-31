<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Branding and outbound-mail configuration for the admin console
     * (docs/PRD.md §85 — central settings, nothing hard-coded). The mail
     * password is stored encrypted (cast on the model); the resource never
     * echoes it back.
     */
    public function up(): void
    {
        Schema::table('organization_settings', function (Blueprint $table) {
            $table->string('app_title')->nullable()->after('company_name');
            $table->string('favicon_path')->nullable()->after('company_logo_path');

            $table->string('mail_from_name')->nullable();
            $table->string('mail_from_address')->nullable();
            $table->string('mail_host')->nullable();
            $table->unsignedInteger('mail_port')->nullable();
            $table->string('mail_username')->nullable();
            $table->text('mail_password')->nullable();
            $table->string('mail_encryption')->nullable(); // tls | ssl | null
        });

        Cache::forget('organization_settings');
    }

    public function down(): void
    {
        Schema::table('organization_settings', function (Blueprint $table) {
            $table->dropColumn([
                'app_title', 'favicon_path',
                'mail_from_name', 'mail_from_address', 'mail_host',
                'mail_port', 'mail_username', 'mail_password', 'mail_encryption',
            ]);
        });
    }
};
