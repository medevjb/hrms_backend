<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/PRD.md §59/§71 — the per-component breakdown of one salary
     * version. A line table rather than a JSON blob because §71 needs each
     * allowance itemised on the payslip and §85 says a business rule with
     * more than one field gets a table.
     */
    public function up(): void
    {
        Schema::create('employee_salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_salary_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_component_id')->constrained();
            $table->decimal('amount', 15, 4);
            $table->timestamps();

            $table->unique(['employee_salary_id', 'salary_component_id'], 'employee_salary_component_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_components');
    }
};
