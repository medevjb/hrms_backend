<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leave_request_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained()->cascadeOnDelete();
            $table->string('stage'); // TEAM_LEADER, OPERATION_MANAGER, HR, HEAD_HR, ADMIN (§41)
            $table->foreignId('approver_user_id')->constrained('users');
            $table->string('decision'); // APPROVED, REJECTED
            $table->text('reason')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_request_approvals');
    }
};
