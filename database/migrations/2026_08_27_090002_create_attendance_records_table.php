<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One summarized row per employee per work_date (docs/PRD.md §25).
     * shift_start_used/shift_end_used/grace_minutes_used are a snapshot,
     * not a live join — §95 requires that changing today's grace setting
     * never rewrites what a past record says was true when it happened.
     */
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained();
            $table->date('work_date');

            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('shift_start_used')->nullable();
            $table->dateTime('shift_end_used')->nullable();
            $table->unsignedInteger('grace_minutes_used')->nullable();

            $table->dateTime('check_in')->nullable();
            $table->dateTime('check_out')->nullable();
            $table->unsignedInteger('worked_minutes')->nullable();
            $table->unsignedInteger('late_minutes')->nullable();

            $table->string('status'); // App\Enums\AttendanceStatus
            $table->boolean('is_manual_adjustment')->default(false);

            $table->timestamps();

            $table->unique(['employee_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
