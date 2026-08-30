<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/PRD.md §56 — the notice document: a signed, dated PDF with
     * closure information and a return date, stored in private local
     * storage (§82). One per holiday; drafted PENDING_APPROVAL by the scan
     * so it has an id for the §91 /holiday-notices/{id}/approve|download
     * routes, then PUBLISHED once Head HR signs it.
     */
    public function up(): void
    {
        Schema::create('holiday_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('holiday_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('holiday_reminder_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->unique(); // e.g. HN-2026-0001
            $table->string('status')->default('PENDING_APPROVAL'); // HolidayNoticeStatus
            $table->string('title');
            $table->text('message');
            $table->text('closure_note')->nullable();
            $table->date('return_date')->nullable();
            $table->string('signatory_name')->nullable(); // §56 Head HR signature
            $table->foreignId('signatory_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable(); // §56 generation date
            $table->string('file_path')->nullable(); // §82 private storage
            $table->foreignId('announcement_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holiday_notices');
    }
};
