<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The organization-wide reporting-month cutoff day (docs/PRD.md §85).
     * When null the reporting month is the calendar month; when set to
     * `C` (1–28) reporting month M runs from day C+1 of M-1 through day C
     * of M, and every date-scoped view/aggregate/API default across the
     * product is computed against that window. Nullable, default null —
     * existing installs keep calendar-month behaviour until an admin sets
     * it.
     */
    public function up(): void
    {
        Schema::table('organization_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('reporting_month_cutoff_day')->nullable()->after('leave_carry_forward_cap_days');
        });

        Cache::forget('organization_settings');
    }

    public function down(): void
    {
        Schema::table('organization_settings', function (Blueprint $table) {
            $table->dropColumn('reporting_month_cutoff_day');
        });

        Cache::forget('organization_settings');
    }
};
