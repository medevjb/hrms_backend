<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/PRD.md §68 — a manual move by an authorised HR user on a draft
     * entry. Every adjustment keeps reason / previous value / new value /
     * actor / timestamp, and produces exactly one payroll_entry_line on the
     * next recalculation.
     */
    public function up(): void
    {
        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_entry_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // PayrollAdjustmentType
            $table->string('label');
            $table->decimal('amount', 15, 4);
            $table->text('reason');
            $table->decimal('previous_value', 15, 4)->nullable();
            $table->decimal('new_value', 15, 4)->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_adjustments');
    }
};
