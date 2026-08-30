<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/PRD.md §57 — company announcements. `audience_type` decides how
     * the audience is resolved at publish time; ALL needs no
     * announcement_targets rows, the other four each read targets of the
     * matching type. A DRAFT is invisible until published (or auto-
     * published once publish_at passes — PublishDueAnnouncementsCommand).
     */
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // AnnouncementType
            $table->string('title');
            $table->text('content');
            $table->string('audience_type'); // AnnouncementAudienceType
            $table->string('status')->default('DRAFT'); // AnnouncementStatus
            $table->string('attachment_path')->nullable(); // §82 private storage
            $table->boolean('acknowledgement_required')->default(false); // §57 EMERGENCY/POLICY
            $table->timestamp('publish_at')->nullable(); // §57 scheduled publish date
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // §57 expiry date
            $table->foreignId('created_by_user_id')->constrained('users');
            // A HOLIDAY announcement born from a notice (§55) links back
            // through holiday_notices.announcement_id — no FK here, to keep
            // the two tables from referencing each other in a cycle.
            $table->unsignedBigInteger('holiday_notice_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'publish_at']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
