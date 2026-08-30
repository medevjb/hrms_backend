<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/PRD.md §61 — the configurable late-penalty policy. Each row is
     * one tier ("3 late days: warning", "5 late days: 0.5 day deduction").
     * Rows sharing an `effective_from` are one policy version; payroll for
     * a period reads the newest version effective on or before the period
     * end, then picks the highest tier the employee's late count reaches
     * (§62 — this is separate from the grace period).
     */
    public function up(): void
    {
        Schema::create('late_penalty_rules', function (Blueprint $table) {
            $table->id();
            $table->date('effective_from');
            $table->unsignedInteger('late_days_threshold'); // tier applies at >= this many qualified late days
            $table->string('outcome'); // LatePenaltyOutcome: WARNING | DEDUCTION
            $table->string('deduction_mode')->nullable(); // LatePenaltyDeductionMode (null for WARNING)
            $table->decimal('deduction_value', 15, 4)->nullable(); // day fraction (0.5) or cash amount
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['effective_from', 'late_days_threshold']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('late_penalty_rules');
    }
};
