<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/PRD.md §72/§146 — money owed (or owed back) from a period that
     * had already finalised when it came to light. The next payroll run
     * for the employee claims every PENDING arrear, adds it as its own
     * payslip line labelled with the original period, and marks it APPLIED.
     * A negative amount is a recovery (§146 — needs payroll.adjust + reason).
     */
    public function up(): void
    {
        Schema::create('payroll_arrears', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('source_type'); // PayrollArrearSourceType
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('original_period_id')->constrained('payroll_periods');
            $table->foreignId('target_period_id')->nullable()->constrained('payroll_periods')->nullOnDelete();
            $table->decimal('amount', 15, 4); // signed — negative is a recovery
            $table->text('reason');
            $table->string('status')->default('PENDING'); // PayrollArrearStatus
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_arrears');
    }
};
