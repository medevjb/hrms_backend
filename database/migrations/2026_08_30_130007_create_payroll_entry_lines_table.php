<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/PRD.md §66/§71/§84 — the itemisation of a payroll entry. One
     * line per earning / deduction; `amount` is always positive and
     * `category` decides its sign. `computed_from` holds the inputs (§141)
     * — e.g. {"days": 2, "daily_salary": "1000.0000"} for an overtime line
     * — so the payslip can explain every number.
     */
    public function up(): void
    {
        Schema::create('payroll_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_entry_id')->constrained()->cascadeOnDelete();
            $table->string('category'); // PayrollLineCategory
            $table->string('type'); // PayrollLineType
            $table->string('label');
            $table->decimal('amount', 15, 4);
            $table->json('computed_from')->nullable();
            $table->string('source_type')->nullable(); // e.g. App\Models\PayrollAdjustment
            $table->unsignedBigInteger('source_id')->nullable();
            $table->boolean('is_manual')->default(false);
            $table->timestamps();

            $table->index(['payroll_entry_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_entry_lines');
    }
};
