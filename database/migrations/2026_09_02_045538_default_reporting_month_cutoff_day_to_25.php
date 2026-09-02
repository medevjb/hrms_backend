<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The reporting month now starts on the 25th by default (docs/PRD.md
     * §85). The column stays nullable — setting it back to null restores
     * plain calendar-month behaviour — but a fresh install, and any row
     * that never got an explicit value, resolves to a cutoff of 25:
     * reporting month M runs from the 26th of M-1 through the 25th of M.
     */
    public function up(): void
    {
        Schema::table('organization_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('reporting_month_cutoff_day')->nullable()->default(25)->change();
        });

        DB::table('organization_settings')
            ->whereNull('reporting_month_cutoff_day')
            ->update(['reporting_month_cutoff_day' => 25]);

        Cache::forget('organization_settings');
    }

    public function down(): void
    {
        Schema::table('organization_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('reporting_month_cutoff_day')->nullable()->default(null)->change();
        });

        Cache::forget('organization_settings');
    }
};
