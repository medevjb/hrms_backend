<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Raw punches (docs/PRD.md §24) — the append-only ledger a daily
     * AttendanceRecord is built from. Never updated, only inserted.
     */
    public function up(): void
    {
        Schema::create('attendance_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained();
            $table->string('event_type'); // App\Enums\AttendanceEventType
            $table->dateTime('event_time');
            $table->string('source'); // App\Enums\AttendanceSource
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'event_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_events');
    }
};
