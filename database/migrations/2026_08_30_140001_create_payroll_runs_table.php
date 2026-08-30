<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/PRD.md §69/§110 — an audit row per draft calculation of a
     * period ("HR Generates Payroll → System Calculates Draft"). Read-only
     * history: who ran it, when, and the totals it produced.
     */
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence'); // 1, 2, 3 within the period
            $table->unsignedInteger('entry_count');
            $table->decimal('gross_total', 15, 4);
            $table->decimal('deduction_total', 15, 4);
            $table->decimal('net_total', 15, 4);
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['payroll_period_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_runs');
    }
};
