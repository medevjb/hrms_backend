<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/PRD.md §57 + §84 mismatch table — "who has seen the policy" for
     * EMERGENCY / POLICY announcements. `acknowledged` distinguishes an
     * explicit "I acknowledge" click from an incidental open.
     */
    public function up(): void
    {
        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->boolean('acknowledged')->default(false);
            $table->timestamp('read_at');
            $table->timestamps();

            $table->unique(['announcement_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_reads');
    }
};
