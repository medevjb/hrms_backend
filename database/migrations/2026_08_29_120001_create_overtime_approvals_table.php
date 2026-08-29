<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('overtime_record_id')->constrained()->cascadeOnDelete();
            $table->string('stage'); // TEAM_LEADER, OPERATION_MANAGER, HR (§50)
            $table->foreignId('approver_user_id')->constrained('users');
            $table->string('decision'); // APPROVED, REJECTED
            $table->text('reason')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_approvals');
    }
};
