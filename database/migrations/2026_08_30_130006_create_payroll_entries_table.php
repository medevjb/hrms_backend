<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/PRD.md §66/§141 — one employee's payroll for one period. Every
     * money column is DECIMAL(15,4). The count columns (late_days, ...) and
     * daily_salary are the inputs the lines were computed from, kept so a
     * disputed payslip (§70) can be recomputed and explained.
     */
    public function up(): void
    {
        Schema::create('payroll_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_salary_id')->nullable()->constrained()->nullOnDelete(); // version used
            $table->string('status')->default('DRAFT'); // PayrollEntryStatus

            $table->decimal('basic_salary', 15, 4)->default(0);
            $table->decimal('daily_salary', 15, 4)->default(0); // §65, snapshot
            $table->unsignedInteger('period_days')->default(0); // divisor days used (§65)
            $table->decimal('late_days', 8, 2)->default(0);
            $table->decimal('absent_days', 8, 2)->default(0);
            $table->decimal('unpaid_leave_days', 8, 2)->default(0);
            $table->decimal('overtime_days', 8, 2)->default(0);

            $table->decimal('gross_earnings', 15, 4)->default(0);
            $table->decimal('total_deductions', 15, 4)->default(0);
            $table->decimal('net_salary', 15, 4)->default(0);

            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['payroll_period_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_entries');
    }
};
