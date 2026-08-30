<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/PRD.md §70/§147 — the dispute an employee raises against their
     * released payroll entry. The reason, the investigation note, and the
     * resolution are all retained and visible to the employee (§147 — "a
     * dispute resolved without an explanation is not resolved").
     */
    public function up(): void
    {
        Schema::create('payroll_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('raised_by_user_id')->constrained('users');
            $table->text('reason');
            $table->string('status')->default('OPEN'); // PayrollDisputeStatus
            $table->string('resolution')->nullable(); // PayrollDisputeResolution
            $table->text('resolution_note')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['payroll_entry_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_disputes');
    }
};
