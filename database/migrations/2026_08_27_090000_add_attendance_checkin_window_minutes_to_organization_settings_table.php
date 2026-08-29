<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/PRD.md §136 introduces "the check-in matching window opens N
     * minutes before shift_start_used and closes at shift_end_used + N,
     * where N is configurable (default 240 minutes)" — a setting §85's
     * organization_settings list never enumerated. Same gap shape as
     * §134.3's table: a rule the document requires but never defined a
     * column for.
     */
    public function up(): void
    {
        Schema::table('organization_settings', function (Blueprint $table) {
            $table->unsignedInteger('attendance_checkin_window_minutes')->default(240)->after('attendance_min_minutes_half_day');
        });

        // OrganizationSettings::current() caches raw attributes forever
        // (App\Models\OrganizationSettings) — an entry written before this
        // column existed is simply missing the key, not null-with-default,
        // so every read of it fails with a TypeError until the cache is
        // forgotten. Found this the hard way mid-development; any migration
        // that adds an organization_settings column needs the same line.
        Cache::forget('organization_settings');
    }

    public function down(): void
    {
        Schema::table('organization_settings', function (Blueprint $table) {
            $table->dropColumn('attendance_checkin_window_minutes');
        });
    }
};
