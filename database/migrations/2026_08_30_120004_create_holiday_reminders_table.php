<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/PRD.md §55 + §84 mismatch table — persisted state so the daily
     * five-day scan never re-creates a reminder or re-notifies Head HR for
     * the same holiday across the five days it stays within range.
     */
    public function up(): void
    {
        Schema::create('holiday_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('holiday_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('lead_days_used'); // §55 = 5, snapshotted (§95)
            $table->date('triggered_on'); // the scan date that fired it
            $table->string('status')->default('PENDING'); // HolidayReminderStatus
            $table->timestamp('head_hr_notified_at')->nullable();
            $table->foreignId('actioned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('actioned_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holiday_reminders');
    }
};
