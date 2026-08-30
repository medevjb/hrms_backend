<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/PRD.md §59 — an effective-dated salary version for an employee.
     * A new version never overwrites the old one: `ended_at` closes the
     * previous row, so payroll for a historical period always reads the
     * version that was in force then (§141 — snapshot the inputs).
     */
    public function up(): void
    {
        Schema::create('employee_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->date('ended_at')->nullable(); // set when a newer version supersedes this
            $table->decimal('basic_salary', 15, 4);
            $table->decimal('gross_monthly', 15, 4); // basic + sum of allowance components, snapshotted
            $table->string('note')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salaries');
    }
};
