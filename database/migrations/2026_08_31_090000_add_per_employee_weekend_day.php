<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/PRD.md §5683 — the weekly off day moves from an organization-wide
     * list to a single organization default plus an optional per-employee
     * override. `weekend_days` (the legacy JSON list) stays on the table so
     * older data and any un-migrated reader keep working; it is kept in sync
     * with `default_weekend_day` on every write.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('weekend_day')->nullable()->after('timezone');
        });

        Schema::table('organization_settings', function (Blueprint $table) {
            $table->string('default_weekend_day')->nullable()->after('weekend_days');
        });

        // Seed the org default from the first day of the existing list.
        $row = DB::table('organization_settings')->first();

        if ($row !== null) {
            $days = json_decode($row->weekend_days ?? '[]', true) ?: [];
            DB::table('organization_settings')
                ->where('id', $row->id)
                ->update(['default_weekend_day' => $days[0] ?? 'friday']);
        }

        Cache::forget('organization_settings');
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('weekend_day');
        });

        Schema::table('organization_settings', function (Blueprint $table) {
            $table->dropColumn('default_weekend_day');
        });
    }
};
