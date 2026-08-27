<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One employee's shift for one specific day, docs/PRD.md §23 — never
     * changes their regular (employee_shifts) assignment.
     */
    public function up(): void
    {
        Schema::create('shift_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained();
            $table->date('work_date');
            $table->foreignId('shift_id')->constrained();
            $table->string('reason');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_overrides');
    }
};
