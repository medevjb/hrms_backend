<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/PRD.md §57 — one row per department / team / role / employee an
     * announcement is aimed at. `target_id` is the primary key of the
     * table named by `target_type` (departments, teams, roles, employees).
     */
    public function up(): void
    {
        Schema::create('announcement_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->string('target_type'); // AnnouncementTargetType
            $table->unsignedBigInteger('target_id');
            $table->timestamps();

            $table->unique(['announcement_id', 'target_type', 'target_id'], 'announcement_targets_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_targets');
    }
};
