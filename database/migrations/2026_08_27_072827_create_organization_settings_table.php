<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Singleton table — see App\Models\OrganizationSettings::current().
     * Every field here is docs/PRD.md §85's list; nothing on this table may
     * ever be hard-coded elsewhere in the application (§125).
     */
    public function up(): void
    {
        Schema::create('organization_settings', function (Blueprint $table) {
            $table->id();

            $table->string('company_name')->default('Agency HRM');
            $table->string('company_logo_path')->nullable();
            $table->string('timezone')->default('UTC'); // §142 — authoritative for attendance
            $table->string('currency')->default('USD');
            $table->unsignedTinyInteger('currency_decimal_places')->default(2);

            $table->unsignedInteger('late_grace_minutes')->default(10); // §101 default

            // No DB-level default: this table is only ever populated through
            // OrganizationSettings::current()'s firstOrCreate(), which
            // supplies every default explicitly (including this one) —
            // simpler than a MySQL JSON expression default for one row.
            $table->json('weekend_days');

            $table->foreignId('default_shift_id')->nullable()->constrained('shifts')->nullOnDelete();

            $table->unsignedTinyInteger('payroll_cutoff_day')->nullable(); // null = 1st -> month end, §63
            $table->string('salary_day_calculation_method')->default('FIXED_30_DAYS');

            $table->boolean('overtime_enabled')->default(true);
            $table->boolean('weekend_overtime_enabled')->default(true);
            $table->boolean('holiday_overtime_enabled')->default(true);
            $table->boolean('hourly_overtime_enabled')->default(false); // §47 OFF by default
            $table->unsignedInteger('overtime_full_day_minutes')->default(480); // §46, 8 hours
            $table->string('overtime_daily_salary_basis')->default('BASIC'); // §143
            $table->string('overtime_hourly_rate_mode')->default('SALARY_DERIVED'); // §48
            $table->decimal('overtime_hourly_fixed_rate', 15, 4)->nullable(); // §141 money precision
            $table->decimal('overtime_hourly_multiplier', 4, 2)->default(1.0);

            $table->boolean('auto_absent_enabled')->default(true); // §137
            $table->string('missing_checkout_policy')->default('LEAVE_OPEN'); // §137
            $table->unsignedInteger('attendance_min_minutes_half_day')->nullable(); // §138

            $table->unsignedTinyInteger('leave_year_start_month')->default(1); // §144
            $table->unsignedInteger('leave_carry_forward_cap_days')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_settings');
    }
};
