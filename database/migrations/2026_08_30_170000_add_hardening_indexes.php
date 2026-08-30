<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/PRD.md §114 — Phase 13 database index review. Adds covering
     * indexes for the filter patterns that ship after Phase 6:
     * status-scoped attendance reports (§99), the payroll-entry
     * confirmation sweep (§147), and announcement-type filtering (§57).
     */
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->index(['status', 'work_date'], 'attendance_records_status_work_date_index');
        });

        Schema::table('payroll_entries', function (Blueprint $table) {
            $table->index(['payroll_period_id', 'acknowledgement_status'], 'payroll_entries_period_ack_index');
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropIndex('attendance_records_status_work_date_index');
        });

        Schema::table('payroll_entries', function (Blueprint $table) {
            $table->dropIndex('payroll_entries_period_ack_index');
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropIndex(['type']);
        });
    }
};
