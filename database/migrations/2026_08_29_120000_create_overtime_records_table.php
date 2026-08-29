<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            // The attendance row that proves the work (§52). Unique: one
            // overtime record per attendance day, which is also what makes
            // detection idempotent (§137 re-runs).
            $table->foreignId('attendance_record_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->string('type'); // WEEKEND / HOLIDAY (§44, §45)
            $table->unsignedInteger('worked_minutes'); // snapshot at detection
            $table->unsignedInteger('full_day_minutes_used'); // §46 threshold, snapshotted (§95)
            $table->decimal('overtime_days', 4, 2)->default(0); // 1.00 qualifying, 0.00 sub-threshold (§46 half-day OFF for V1)
            $table->string('status'); // §51
            $table->string('current_stage')->nullable(); // §50 chain position; null once terminal
            $table->text('rejection_reason')->nullable();
            $table->foreignId('rejected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            // §68 manual grant/adjustment, carried inline like leave_requests'
            // direct-approval fields.
            $table->decimal('manual_days_override', 4, 2)->nullable();
            $table->text('manual_adjustment_reason')->nullable();
            $table->foreignId('adjusted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('adjusted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('payroll_processed_at')->nullable(); // §72 — set by Phase 8/9
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index('work_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_records');
    }
};
