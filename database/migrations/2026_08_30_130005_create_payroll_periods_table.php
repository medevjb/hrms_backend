<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/PRD.md §63/§64 — a real payroll period record. The cutoff day
     * and salary-day method in force when it was created are snapshotted
     * (§64 — "historical periods must not be changed by future payroll
     * cutoff updates"). Boundaries are organization-timezone dates (§142).
     */
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->string('label')->unique(); // "August 2026"
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('UPCOMING'); // PayrollPeriodStatus
            $table->unsignedInteger('cutoff_day_used')->nullable(); // snapshot (§64)
            $table->string('salary_day_calculation_method_used'); // snapshot (§64, §65)
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->unique(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');
    }
};
