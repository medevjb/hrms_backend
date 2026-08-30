<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/PRD.md §71/§84 — the payslip document, generated at
     * finalisation. Stored privately (§82) and streamed through an
     * authorised endpoint. The totals are snapshotted so the payslip
     * stays fixed even if a later arrear touches the entry.
     */
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_entry_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained();
            $table->foreignId('employee_id')->constrained();
            $table->string('reference')->unique(); // PS-2026-08-0001
            $table->decimal('gross_earnings', 15, 4);
            $table->decimal('total_deductions', 15, 4);
            $table->decimal('net_salary', 15, 4);
            $table->string('file_path');
            $table->timestamp('generated_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
