<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            // Every employee has exactly one paired login account, created
            // together at invite time (docs/PRD.md §148 #2). Login email
            // lives on users.email — not duplicated here.
            $table->foreignId('user_id')->unique()->constrained();

            $table->string('employee_code')->unique();

            // Personal information — docs/PRD.md §12
            $table->string('first_name');
            $table->string('last_name');
            $table->string('profile_image_path')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();

            // Employment — department/team/Team Leader/Operation Manager are
            // derived through team_members + teams, not stored here, so a
            // transfer (§14) can't leave two contradictory sources of truth.
            $table->date('joining_date');
            $table->string('designation');
            $table->string('employment_type');
            $table->string('status'); // App\Enums\EmployeeStatus — docs/PRD.md §13
            $table->date('confirmation_date')->nullable();

            // Work configuration — docs/PRD.md §12. default_shift_id is
            // added once shifts exist (Phase 3).
            $table->string('office_location')->nullable();
            $table->string('timezone')->nullable(); // display-only — §142
            $table->boolean('overtime_eligible')->default(true);

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
