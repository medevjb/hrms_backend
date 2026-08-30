<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/PRD.md §69/§70/§147 — the entry-level state the Phase 9 review
     * and confirmation flow needs on top of Phase 8's calculation columns.
     */
    public function up(): void
    {
        Schema::table('payroll_entries', function (Blueprint $table) {
            $table->string('acknowledgement_status')->default('PENDING')->after('status'); // PayrollAcknowledgementStatus
            $table->timestamp('released_at')->nullable()->after('calculated_at');
            $table->timestamp('acknowledged_at')->nullable()->after('released_at');
            $table->timestamp('finalized_at')->nullable()->after('acknowledged_at');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_entries', function (Blueprint $table) {
            $table->dropColumn(['acknowledgement_status', 'released_at', 'acknowledged_at', 'finalized_at']);
        });
    }
};
