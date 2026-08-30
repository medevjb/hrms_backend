<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/PRD.md §66/§147 — payroll toggles that aren't already on the
     * organization_settings singleton (§85 keeps `payroll_cutoff_day` and
     * `salary_day_calculation_method` there). Singleton, read through
     * PayrollSettings::current().
     */
    public function up(): void
    {
        Schema::create('payroll_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('late_penalty_enabled')->default(true);
            $table->boolean('absence_deduction_enabled')->default(true);
            $table->boolean('unpaid_leave_deduction_enabled')->default(true);
            $table->boolean('overtime_earnings_enabled')->default(true);
            $table->unsignedInteger('dispute_window_days')->default(7); // §147
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_settings');
    }
};
